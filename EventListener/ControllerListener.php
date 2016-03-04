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

use Symfonian\Indonesia\RehatBundle\Annotation\Crud;
use Symfonian\Indonesia\RehatBundle\Controller\RehatControllerTrait;
use Symfonian\Indonesia\RehatBundle\Extractor\ExtractorFactory;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;

/**
 * @author Muhammad Surya Ihsanuddin <surya.kejawen@gmail.com>
 */
class ControllerListener
{
    /**
     * @var ExtractorFactory
     */
    private $extractor;

    private $form;
    private $entity;

    public function __construct(ExtractorFactory $extractor)
    {
        $this->extractor = $extractor;

        $this->crud = new Crud();
    }

    public function parseAnnotation(FilterControllerEvent $event)
    {
        $controller = $event->getController();
        if (!is_array($controller)) {
            return false;
        }

        $controller = $controller[0];
        $reflectionObject = new \ReflectionObject($controller);
        unset($controller);

        $allow = false;
        foreach ($reflectionObject->getTraits() as $trait) {
            if ($trait->getName() === RehatControllerTrait::class) {
                $allow = true;
                break;
            }
        }

        if (!$allow) {
            return false;
        }

        $this->extractor->extract($reflectionObject);
        foreach ($this->extractor->getClassAnnotations() as $annotation) {
            if ($annotation instanceof Crud) {
                $this->entity = $annotation->getEntity();
                $this->form = $annotation->getForm();

                unset($annotation);
                break;
            }
        }
    }

    /**
     * @return string
     */
    public function getForm()
    {
        return $this->form;
    }

    /**
     * @return string
     */
    public function getEntity()
    {
        return $this->entity;
    }
}
