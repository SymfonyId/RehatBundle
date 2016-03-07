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
use Symfonian\Indonesia\RehatBundle\Extractor\ExtractorFactory;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;

/**
 * @author Muhammad Surya Ihsanuddin <surya.kejawen@gmail.com>
 */
class ControllerListener extends AbstractListener
{
    /**
     * @var ExtractorFactory
     */
    private $extractor;

    private $form;
    private $entity;

    /**
     * @param ExtractorFactory $extractor
     */
    public function __construct(ExtractorFactory $extractor)
    {
        $this->extractor = $extractor;
    }

    /**
     * @param FilterControllerEvent $event
     *
     * @return bool
     */
    public function onKernelController(FilterControllerEvent $event)
    {
        if (!$this->isValid($event)) {
            return;
        }

        $this->extractor->extract($this->getReflectionController());
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
