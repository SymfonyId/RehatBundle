<?php

/*
 * This file is part of the RehatBundle package.
 *
 * (c) Muhammad Surya Ihsanuddin <surya.kejawen@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfonian\Indonesia\RehatBundle\EventListener;

use Doctrine\Common\Annotations\Reader;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;
use Symfonian\Indonesia\RehatBundle\Annotation\Filterable;
use Symfonian\Indonesia\RehatBundle\Annotation\Searchable;
use Symfonian\Indonesia\RehatBundle\Event\FilterQueryEvent;
use Symfonian\Indonesia\RehatBundle\SymfonianIndonesiaRehatConstants as Constants;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;

/**
 * @author Muhammad Surya Ihsanuddin <surya.kejawen@gmail.com>
 */
class FilterQueryListener extends AbstractQueryListener
{
    /**
     * @var Reader
     */
    private $reader;

    private $filters = array();

    /**
     * @param EntityManager $entityManager
     * @param Reader        $reader
     */
    public function __construct(EntityManager $entityManager, Reader $reader)
    {
        parent::__construct($entityManager);
        $this->reader = $reader;
    }

    /**
     * @param GetResponseEvent $event
     */
    public function onKernelRequest(GetResponseEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!$request->isMethod('GET')) {
            return;
        }

        $this->filters = array_filter($request->query->all(), function ($value, $key) {
            return !('q' === $key || 'sort_by' === $key || 'page' === $key || 'limit' === $key);
        });
    }

    /**
     * @param FilterQueryEvent $event
     */
    public function onFilterQuery(FilterQueryEvent $event)
    {
        if (empty($this->filters)) {
            return;
        }

        $entity = $event->getEntityClass();
        $filters = $this->getFilters(new \ReflectionClass($entity));
        if (!$filters) {
            return;
        }

        $this->applyFilter($this->getClassMetadata($entity), $event->getQueryBuilder(), $filters, $this->filters);
    }

    /**
     * @param \ReflectionClass $reflection
     *
     * @return array
     */
    private function getFilters(\ReflectionClass $reflection)
    {
        $filters = array();
        foreach ($reflection->getProperties() as $reflectionProperty) {
            foreach ($this->reader->getPropertyAnnotations($reflectionProperty) as $annotation) {
                if ($annotation instanceof Filterable) {
                    $filters[] = $reflectionProperty->getName();
                }
            }
        }

        return $filters;
    }

    /**
     * @param ClassMetadata $metadata
     * @param QueryBuilder  $queryBuilder
     * @param array         $filterFields
     * @param array         $filters
     */
    private function applyFilter(ClassMetadata $metadata, QueryBuilder $queryBuilder, array $filterFields, $filters)
    {
        foreach ($this->getMapping($metadata, $filterFields) as $key => $value) {
            if (array_key_exists('join', $value)) {
                if (in_array($value['join_field'], array_keys($filters))) {
                    $queryBuilder->leftJoin(sprintf('%s.%s', Constants::ENTITY_ALIAS, $value['join_field']), $value['join_alias'], 'WITH');
                    $this->buildFilter($queryBuilder, $value, $value['join_alias'], $filters[$value['join_field']]);
                }
            } else {
                if (in_array($value, array_keys($filters))) {
                    $this->buildFilter($queryBuilder, $value, Constants::ENTITY_ALIAS, $filters[$value]);
                }
            }
        }
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @param array        $metadata
     * @param $alias
     * @param $filter
     */
    private function buildFilter(QueryBuilder $queryBuilder, array $metadata, $alias, $filter)
    {
        if (in_array($metadata['type'], array('date', 'datetime', 'time'))) {
            $date = \DateTime::createFromFormat($this->getContainer()->getParameter('sir.date_format'), $filter);
            if ($date) {
                $queryBuilder->andWhere(sprintf('%s.%s = :%s', $alias, $metadata['fieldName'], $metadata['fieldName']));
                $queryBuilder->setParameter($metadata['fieldName'], $date->format('Y-m-d'));
            }
        } else {
            $queryBuilder->orWhere(sprintf('%s.%s = :%s', $alias, $metadata['fieldName'], $metadata['fieldName']));
            $queryBuilder->setParameter($metadata['fieldName'], strtr('%filter%', array('filter' => $filter)));
        }
    }

    /**
     * @param ClassMetadata $metadata
     * @param array         $fields
     *
     * @return array
     *
     * @throws \Doctrine\ORM\Mapping\MappingException
     */
    protected function getMapping(ClassMetadata $metadata, array $fields)
    {
        $filters = array();
        foreach ($fields as $field) {
            $fieldName = $metadata->getFieldName($field);
            try {
                $filters[] = $metadata->getFieldMapping($fieldName);
            } catch (\Exception $ex) {
                $mapping = $metadata->getAssociationMapping($fieldName);
                $associationMatadata = $this->getClassMetadata($mapping['targetEntity']);
                if ($filter = $this->getFilters(new \ReflectionClass($mapping['targetEntity']))) {
                    $filters[] = array_merge(array(
                        'join' => true,
                        'join_field' => $fieldName,
                        'join_alias' => $this->getAlias(),
                    ), $associationMatadata->getFieldMapping($filter[0]));
                }
            }
        }

        return $filters;
    }
}
