<?php

/*
 * This file is part of the RehatBundle package.
 *
 * (c) Muhammad Surya Ihsanuddin <surya.kejawen@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfonian\Indonesia\RehatBundle\Controller;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\QueryBuilder;
use FOS\RestBundle\View\View;
use Hateoas\Configuration\Route;
use Hateoas\Representation\CollectionRepresentation;
use Hateoas\Representation\Factory\PagerfantaFactory;
use Pagerfanta\Adapter\DoctrineORMAdapter;
use Pagerfanta\Pagerfanta;
use Symfonian\Indonesia\RehatBundle\Event\FilterEntityEvent;
use Symfonian\Indonesia\RehatBundle\Event\FilterFormEvent;
use Symfonian\Indonesia\RehatBundle\Event\FilterQueryEvent;
use Symfonian\Indonesia\RehatBundle\Model\EntityInterface;
use Symfonian\Indonesia\RehatBundle\SymfonianIndonesiaRehatConstants as Constants;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * @author Muhammad Surya Ihsanuddin <surya.kejawen@gmail.com>
 */
trait RehatControllerTrait
{
    /**
     * @param $serviceId
     *
     * @return object
     */
    abstract protected function get($serviceId);

    /**
     * @param $parameter
     *
     * @return object
     */
    abstract protected function getParameter($parameter);

    /**
     * @param View $view
     *
     * @return Response
     */
    abstract protected function handleView(View $view);

    /**
     * @param Request         $request
     * @param FormInterface   $form
     * @param EntityInterface $entity
     *
     * @return Response
     */
    protected function post(Request $request, FormInterface $form, EntityInterface $entity)
    {
        return $this->handle($request, $form, $entity, new View());
    }

    /**
     * @param FormInterface $form
     *
     * @return Response
     */
    protected function create(FormInterface $form)
    {
        return $this->handleView(new View($this->flattenForm($form)));
    }

    /**
     * @param Request       $request
     * @param FormInterface $form
     * @param int           $id
     * @param string        $entityClass
     *
     * @return Response
     */
    protected function put(Request $request, FormInterface $form, $id, $entityClass)
    {
        /** @var EntityInterface $entity */
        $entity = $this->find($entityClass, $id);

        $view = new View();
        if (!$entity) {
            $view->setData($this->getErrorFormat($this->translate('not_found'), Response::HTTP_NOT_FOUND));
            $view->setStatusCode(Response::HTTP_NOT_FOUND);
        }

        return $this->handle($request, $form, $entity, $view);
    }

    /**
     * @param FormInterface $form
     * @param int           $id
     * @param string        $entityClass
     *
     * @return Response
     */
    protected function edit(FormInterface $form, $id, $entityClass)
    {
        /** @var EntityInterface $entity */
        $entity = $this->find($entityClass, $id);

        $view = new View();
        if (!$entity) {
            $view->setData($this->getErrorFormat($this->translate('not_found'), Response::HTTP_NOT_FOUND));
            $view->setStatusCode(Response::HTTP_NOT_FOUND);
        } else {
            $form->setData($entity);
            $view->setData($this->flattenForm($form));

            $event = new FilterFormEvent();
            $event->setData($entity);
            $event->setForm($form);
            $this->fireEvent(Constants::PRE_FORM_VIEW, $event);

            $response = $event->getResponse();
            if ($response) {
                return $response;
            }
        }

        return $this->handleView($view);
    }

    /**
     * @param int    $id
     * @param string $entityClass
     *
     * @return Response
     */
    protected function delete($id, $entityClass)
    {
        /** @var EntityInterface $entity */
        $entity = $this->find($entityClass, $id);
        $view = new View();
        if (!$entity) {
            $view->setData($this->getErrorFormat($this->translate('not_found'), Response::HTTP_NOT_FOUND));
            $view->setStatusCode(Response::HTTP_NOT_FOUND);
        }

        $event = new FilterEntityEvent();
        $event->setEntity($entity);
        $event->setEntityManager($this->getManager());
        $this->fireEvent(Constants::PRE_DELETE, $event);

        if ($event->getResponse()) {
            return $event->getResponse();
        }

        if (!$this->remove($entity)) {
            $view->setData($this->getErrorFormat($this->translate('cant_delete'), Response::HTTP_CONFLICT));
            $view->setStatusCode(Response::HTTP_CONFLICT);
        } else {
            $view->setStatusCode(Response::HTTP_NO_CONTENT);
        }

        return $this->handleView($view);
    }

    /**
     * @param Request         $request
     * @param EntityInterface $entity
     *
     * @return Response
     */
    protected function getCollection(Request $request, EntityInterface $entity)
    {
        $requestParams = $this->getRequestParam($request);
        $reflection = new \ReflectionClass($entity);
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = $this->getManager()->createQueryBuilder();
        $queryBuilder->select(Constants::ENTITY_ALIAS);
        $queryBuilder->from($entity, Constants::ENTITY_ALIAS);

        $filterList = new FilterQueryEvent();
        $filterList->setQueryBuilder($queryBuilder);
        $filterList->setAlias(Constants::ENTITY_ALIAS);
        $filterList->setEntityClass($entity);
        $this->fireEvent(Constants::FILTER_LIST, $filterList);

        $query = $queryBuilder->getQuery();
        $query->useQueryCache(true);
        $query->useResultCache(true, 1, serialize($query->getParameters()));

        $pagerAdapter = new DoctrineORMAdapter($queryBuilder);
        $pager = new Pagerfanta($pagerAdapter);
        $pager->setCurrentPage($requestParams['page']);
        $pager->setMaxPerPage($requestParams['limit']);

        $embed = strtolower($reflection->getShortName()).'s';
        $pagerFactory = new PagerfantaFactory();
        $representation = $pagerFactory->createRepresentation(
            $pager,
            new Route($request->get('_route'), $requestParams),
            new CollectionRepresentation($pager->getCurrentPageResults(), $embed, $embed)
        );

        return $this->handleView(new View($representation));
    }

    /**
     * @param int    $id
     * @param string $entityClass
     *
     * @return Response
     */
    protected function getSingle($id, $entityClass)
    {
        /** @var EntityInterface $entity */
        $entity = $this->find($entityClass, $id);

        $view = new View();
        if (!$entity) {
            $view->setData($this->getErrorFormat($this->translate('not_found'), Response::HTTP_NOT_FOUND));
            $view->setStatusCode(Response::HTTP_NOT_FOUND);
        } else {
            $view->setData($entity);
        }

        return $this->handleView($view);
    }

    /**
     * @param string $form
     * @param string $method
     *
     * @return \Symfony\Component\Form\Form
     */
    protected function getForm($form, $method = 'POST')
    {
        try {
            $formObject = $this->get($form);
        } catch (\Exception $ex) {
            $formObject = new $form();
        }

        /** @var FormBuilder $form */
        $form = $this->get('form.factory')->createBuilder(get_class($formObject));
        $form->setMethod(strtoupper($method));

        return $form->getForm();
    }

    /**
     * @param $name
     * @param $handler
     */
    private function fireEvent($name, $handler)
    {
        $dispatcher = $this->get('event_dispatcher');
        $dispatcher->dispatch($name, $handler);
    }

    /**
     * @param Request         $request
     * @param FormInterface   $form
     * @param EntityInterface $data
     * @param View            $view
     *
     * @return Response
     */
    private function handle(Request $request, FormInterface $form, EntityInterface $data, View $view)
    {
        $event = new FilterFormEvent();
        $event->setData($data);
        $event->setForm($form);
        $this->fireEvent(Constants::PRE_FORM_SUBMIT, $event);

        $response = $event->getResponse();
        if ($response) {
            return $response;
        }

        $form->handleRequest($request);
        if ($form->isValid()) {
            $formData = $form->getData();
            $view->setData($formData);
            if ($formData->getId()) {
                $view->setStatusCode(Response::HTTP_ACCEPTED);
            } else {
                $view->setStatusCode(Response::HTTP_CREATED);
            }
            $this->save($formData);
        } else {
            $view->setData($this->getErrorFormat($form->getErrors(), Response::HTTP_NOT_ACCEPTABLE));
            $view->setStatusCode(Response::HTTP_NOT_ACCEPTABLE);
        }

        return $this->handleView($view);
    }

    /**
     * @param EntityInterface $entity
     */
    private function save(EntityInterface $entity)
    {
        $entityManager = $this->getManager();
        $entityManager->persist($entity);
        $entityManager->flush();
    }

    /**
     * @param EntityInterface $entity
     *
     * @return bool
     */
    private function remove(EntityInterface $entity)
    {
        $entityManager = $this->getManager();
        try {
            $entityManager->remove($entity);
            $entityManager->flush();

            return true;
        } catch (\Exception $ex) {
            return false;
        }
    }

    /**
     * @return EntityManager
     */
    private function getManager()
    {
        return $this->get('doctrine')->getManager();
    }

    /**
     * @param $entityClass
     * @param $id
     *
     * @return null|object
     */
    private function find($entityClass, $id)
    {
        return $this->getManager()->getRepository($entityClass)->find($id);
    }

    /**
     * @param $message
     * @param array  $paramters
     * @param string $translationDomain
     *
     * @return string
     */
    private function translate($message, array $paramters = array(), $translationDomain = Constants::TRANSLATION_DOMAIN)
    {
        /** @var TranslatorInterface $tranlator */
        $tranlator = $this->get('translator');

        return $tranlator->trans($message, $paramters, $translationDomain);
    }

    /**
     * @param $message
     * @param int $statusCode
     *
     * @return array
     */
    private function getErrorFormat($message, $statusCode = Response::HTTP_OK)
    {
        return array('message' => $message, 'code' => $statusCode);
    }

    /**
     * @param FormInterface $form
     *
     * @return array|mixed
     */
    private function flattenForm(FormInterface $form)
    {
        if (empty(!$form->all())) {
            $result = array();
            $result[$form->getName()] = array();
            foreach ($form->all() as $name => $child) {
                $result[$form->getName()][$name] = $this->flattenForm($child);
            }

            return $result;
        }
        $view = $form->createView();

        return array(
            'name' => $view->vars['full_name'],
            'type' => $view->vars['block_prefixes'][1],
            'required' => array_key_exists('required', $view->vars)? $view->vars['required']: false,
            'data' => $form->getData(),
        );
    }

    /**
     * @param Request $request
     *
     * @return array
     */
    private function getRequestParam(Request $request)
    {
        $params = array(
            'page' => $request->query->get('page', 1),
            'limit' => $request->query->get('limit', $this->getParameter('sir.limit')),
        );

        if ($filter = $request->query->get('filter')) {
            $params['filter'] = $filter;
        }

        if ($sortBy = $request->query->get('sort_by')) {
            $params['sort_by'] = $sortBy;
        }

        return $params;
    }
}
