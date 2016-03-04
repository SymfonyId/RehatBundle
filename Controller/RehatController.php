<?php

/*
 * This file is part of the AdminBundle package.
 *
 * (c) Muhammad Surya Ihsanuddin <surya.kejawen@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfonian\Indonesia\RehatBundle;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\QueryBuilder;
use FOS\RestBundle\Controller\FOSRestController as Controller;
use FOS\RestBundle\View\View;
use Hateoas\Configuration\Route;
use Hateoas\Representation\CollectionRepresentation;
use Hateoas\Representation\Factory\PagerfantaFactory;
use Pagerfanta\Adapter\DoctrineORMAdapter;
use Pagerfanta\Pagerfanta;
use Symfonian\Indonesia\CoreBundle\Toolkit\DoctrineManager\Model\EntityInterface;
use Symfonian\Indonesia\RehatBundle\Event\FilterEntityEvent;
use Symfonian\Indonesia\RehatBundle\Event\FilterFormEvent;
use Symfonian\Indonesia\RehatBundle\Event\FilterQueryEvent;
use Symfonian\Indonesia\RehatBundle\SymfonianIndonesiaRehatConstants as Constants;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * @author Muhammad Surya Ihsanuddin <surya.kejawen@gmail.com>
 */
class RehatController extends Controller
{
    public function listAction(Request $request)
    {
        $perPage = $request->query->getInt('limit', 10);
        $page = $request->query->getInt('page', 1);
        $entity = $request->get('_entity');
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
        $pager->setCurrentPage($page);
        $pager->setMaxPerPage($perPage);

        $pagerFactory = new PagerfantaFactory();
        $representation = $pagerFactory->createRepresentation(
            $pager,
            new Route($request->get('_route'), array('limit' => $perPage, 'page' => $page)),
            new CollectionRepresentation($pager->getCurrentPageResults(), $reflection->getShortName(), $reflection->getShortName())
        );

        return $this->handleView(new View($representation));
    }

    public function createAction(Request $request)
    {
        $form = $this->getForm($request->get('_form'));
        $entity = $request->get('_entity');

        return $this->handle($request, $form, new $entity(), new View());
    }

    public function updateAction(Request $request, $id)
    {
        $form = $this->getForm($request->get('_form'));
        /** @var EntityInterface $entity */
        $entity = $this->find($request->get('_entity'), $id);

        $view = new View();
        if (!$entity) {
            $view->setData($this->getErrorFormat($this->translate('not_found'), Response::HTTP_NOT_FOUND));
            $view->setStatusCode(Response::HTTP_NOT_FOUND);
        }

        return $this->handle($request, $form, $entity, $view);
    }

    public function deleteAction(Request $request, $id)
    {
        /** @var EntityInterface $entity */
        $entity = $this->find($request->get('_entity'), $id);
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

    private function fireEvent($name, $handler)
    {
        $dispatcher = $this->container->get('event_dispatcher');
        $dispatcher->dispatch($name, $handler);
    }

    private function getForm($form)
    {
        try {
            $formObject = $this->container->get($form);
        } catch (\Exception $ex) {
            $formObject = new $form();
        }

        /** @var FormInterface $form */
        $form = $this->container->get('form.factory')->create(get_class($formObject));

        return $form;
    }

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

    private function save(EntityInterface $entity)
    {
        $entityManager = $this->getManager();
        $entityManager->persist($entity);
        $entityManager->flush();
    }

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
        return $this->getDoctrine()->getManager();
    }

    private function find($entityClass, $id)
    {
        return $this->getManager()->getRepository($entityClass)->find($id);
    }

    private function translate($message, array $paramters = array(), $translationDomain = Constants::TRANSLATION_DOMAIN)
    {
        /** @var TranslatorInterface $tranlator */
        $tranlator = $this->container->get('translator');

        return $tranlator->trans($message, $paramters, $translationDomain);
    }

    private function getErrorFormat($message, $statusCode = Response::HTTP_OK)
    {
        return array('message' => $message, 'code' => $statusCode);
    }
}
