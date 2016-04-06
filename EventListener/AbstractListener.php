<?php

namespace Symfonian\Indonesia\RehatBundle\EventListener;

use Symfonian\Indonesia\RehatBundle\Controller\RehatControllerTrait;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;

class AbstractListener
{
    /**
     * @var RehatControllerTrait
     */
    private $controller;

    /**
     * @var \ReflectionObject
     */
    private $reflectionController;

    /**
     * @param FilterControllerEvent $event
     *
     * @return bool
     */
    protected function isValid(FilterControllerEvent $event)
    {
        $controller = $event->getController();
        if (!is_array($controller)) {
            return false;
        }

        $controller = $controller[0];
        $this->reflectionController = new \ReflectionObject($controller);
        $this->controller = $controller;
        unset($controller);

        $allow = false;
        foreach ($this->reflectionController->getTraits() as $trait) {
            if ($trait->getName() === RehatControllerTrait::class) {//Only RehatControllerTrait can use this listener
                $allow = true;
                break;
            }
        }

        return $allow;
    }

    /**
     * @return RehatControllerTrait
     */
    protected function getController()
    {
        return $this->controller;
    }

    /**
     * @return \ReflectionObject
     */
    protected function getReflectionController()
    {
        return $this->reflectionController;
    }
}
