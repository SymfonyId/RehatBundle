<?php

/*
 * This file is part of the RehatBundle package.
 *
 * (c) Muhammad Surya Ihsanuddin <surya.kejawen@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfonian\Indonesia\RehatBundle\Route;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfonian\Indonesia\RehatBundle\Controller\RehatControllerTrait;
use Symfonian\Indonesia\RehatBundle\Extractor\ExtractorFactory;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerNameParser;
use Symfony\Bundle\FrameworkBundle\Routing\DelegatingLoader;
use Symfony\Component\Config\Loader\LoaderResolverInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Route as SymfonyRoute;
use Symfony\Component\Routing\RouteCollection;

/**
 * @author Muhammad Surya Ihsanuddin <surya.kejawen@gmail.com>
 */
class SirRouteLoader extends DelegatingLoader
{
    /**
     * @var ExtractorFactory
     */
    private $extractor;

    /**
     * @var KernelInterface
     */
    private $kernel;

    /**
     * SiabRouteLoader constructor.
     *
     * @param ControllerNameParser    $parser
     * @param LoaderResolverInterface $resolver
     * @param ExtractorFactory        $extractor
     * @param KernelInterface         $kernel
     */
    public function __construct(ControllerNameParser $parser, LoaderResolverInterface $resolver, ExtractorFactory $extractor, KernelInterface $kernel)
    {
        $this->extractor = $extractor;
        $this->kernel = $kernel;
        parent::__construct($parser, $resolver);
    }

    /**
     * @param string $resource
     * @param null   $type
     *
     * @return RouteCollection
     */
    public function load($resource, $type = null)
    {
        $collection = new RouteCollection();
        $controllers = $this->findAllControllerFromDir($this->getControllerDir($resource));
        /** @var \ReflectionClass $controller */
        foreach ($controllers as $controller) {
            foreach ($controller->getTraits() as $trait) {
                if ($trait->getName() === RehatControllerTrait::class) {
                    $this->registerRoute($collection, $controller);
                } else {
                    $collection->addCollection(parent::load($resource, 'annotation'));
                }
            }
        }

        return $collection;
    }

    /**
     * @param string $resource
     * @param null   $type
     *
     * @return bool
     */
    public function supports($resource, $type = null)
    {
        return 'sir' === $type;
    }

    /**
     * @param $resource
     *
     * @return array|string
     */
    private function getControllerDir($resource)
    {
        return $this->kernel->locateResource($resource);
    }

    /**
     * @param $dir
     *
     * @return array
     */
    private function findAllControllerFromDir($dir)
    {
        $finder = new Finder();
        $finder->name('*Controller.php')->in($dir);

        $controllers = array();
        /** @var SplFileInfo $file */
        foreach ($finder as $file) {
            $controllers[] = $this->getReflectionClass($file);
        }

        return $controllers;
    }

    /**
     * @param SplFileInfo $file
     *
     * @return \ReflectionClass|void
     */
    private function getReflectionClass(SplFileInfo $file)
    {
        $namespace = null;
        if (preg_match('#^namespace\s+(.+?);$#sm', $file->getContents(), $matches)) {
            $namespace = $matches[1];
        }

        $controller = null;
        if ($namespace) {
            $name = substr($file->getRelativePathname(), 0, -4);
            $controller = sprintf('%s\\%s', $namespace, $name);
        }

        if ($controller) {
            return new \ReflectionClass($controller);
        }

        return;
    }

    /**
     * @param RouteCollection  $collection
     * @param \ReflectionClass $controller
     */
    private function registerRoute(RouteCollection $collection, \ReflectionClass $controller)
    {
        $route = $this->parseController($controller) ?: new Route(array('path' => ''));

        foreach ($controller->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if (strpos(strtolower($method), 'action')) {
                $prefixName = str_replace('\\', '_', $controller->getName());
                $collection->addCollection($this->compileRoute($prefixName, $controller, $method, $route));
            }
        }
    }

    /**
     * @param \ReflectionClass $reflectionClass
     *
     * @return null| Route
     */
    private function parseController(\ReflectionClass $reflectionClass)
    {
        $this->extractor->extract($reflectionClass);
        foreach ($this->extractor->getClassAnnotations() as $annotation) {
            if ($annotation instanceof Route) {
                return $annotation;
            }
        }

        return;
    }

    /**
     * @param $prefixName
     * @param \ReflectionClass  $class
     * @param \ReflectionMethod $method
     * @param Route|null        $route
     *
     * @return RouteCollection
     */
    private function compileRoute($prefixName, \ReflectionClass $class, \ReflectionMethod $method, Route $route = null)
    {
        $collection = new RouteCollection();

        $this->extractor->extract($method);
        $routeAnnotations = array();
        $methodAnnotaion = null;
        foreach ($this->extractor->getMethodAnnotations() as $key => $annoation) {
            if ($annoation instanceof Route) {
                $routeAnnotations[] = $annoation;
            }
            if ($annoation instanceof Method) {
                $methodAnnotaion = $annoation;
            }
        }

        $name = $route->getName() ?: strtolower($prefixName.'_'.$method->getName());
        if (empty($routeAnnotations)) {
            $this->addRoute($class, $method, $collection, $name, $route, null, null);
        } else {
            foreach ($routeAnnotations as $routeAnnotation) {
                /* @var Route $routeAnnotation */
                $this->addRoute($class, $method, $collection, $name, $route, $routeAnnotation, $methodAnnotaion);
            }
        }

        return $collection;
    }

    /**
     * @param \ReflectionClass  $reflectionClass
     * @param \ReflectionMethod $reflectionMethod
     * @param RouteCollection   $collection
     * @param $name
     * @param Route|null  $controllerRoute
     * @param Route|null  $route
     * @param Method|null $method
     */
    private function addRoute(\ReflectionClass $reflectionClass, \ReflectionMethod $reflectionMethod, RouteCollection $collection, $name, Route $controllerRoute = null, Route $route = null, Method $method = null)
    {
        $controller = $reflectionClass->getName().'::'.$reflectionMethod->getName();
        $methodName = str_replace('action', '', strtolower($reflectionMethod->getName()));

        $routeAction = $route ?: $this->generateRoute($methodName);
        $methodAction = $method ?: $this->generateMethod($methodName);

        $path = '';
        if ($controllerRoute) {
            $path = $controllerRoute->getPath();
        }
        $path = $path.$routeAction->getPath();

        $symfonyRoute = new SymfonyRoute(
            $path,
            array_merge($routeAction->getDefaults(), array('_controller' => $controller)),
            $routeAction->getRequirements(),
            $routeAction->getOptions(),
            $routeAction->getHost(),
            $routeAction->getSchemes(),
            $method ? $method->getMethods() : $methodAction->getMethods(),
            $routeAction->getCondition()
        );

        $routeName = $route && $route->getName() ? $route->getName() : substr(str_replace(array('bundle', 'controller', '__'), array('', '', '_'), $name), 0, -6);
        $collection->add($routeName, $symfonyRoute);
    }

    /**
     * @param $methodName
     *
     * @return Method
     */
    private function generateMethod($methodName)
    {
        switch ($methodName) {
            case 'post' :
                return new Method(array(
                    'methods' => array('POST'),
                ));
                break;
            case 'put':
                return new Method(array(
                    'methods' => array('PUT'),
                ));
                break;
            case 'get':
            case 'cget':
            case 'new':
            case 'edit':
                return new Method(array(
                    'methods' => array('GET'),
                ));
                break;
            case 'delete':
                return new Method(array(
                    'methods' => array('DELETE'),
                ));
                break;
        }

        return new Method(array(
            'methods' => array(),
        ));
    }

    /**
     * @param $methodName
     * @return Route
     */
    private function generateRoute($methodName)
    {
        $route = new Route(array());

        switch ($methodName) {
            case 'new':
                $route->setPath('/new');
                break;
            case 'edit':
                $route->setPath('/{id}/edit');
                break;
            case 'put':
            case 'delete':
            case 'get':
                $route->setPath('/{id}');
                $route->setRequirements(array('id' => '\d+'));
                break;
        }

        return $route;
    }
}
