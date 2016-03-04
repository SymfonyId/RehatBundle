<?php

/*
 * This file is part of the RehatBundle package.
 *
 * (c) Muhammad Surya Ihsanuddin <surya.kejawen@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfonian\Indonesia\RehatBundle\Annotation;

/**
 * @Annotation
 * @Target({"CLASS"})
 *
 * @author Muhammad Surya Ihsanuddin <surya.kejawen@gmail.com>
 */
class Crud
{
    private $entity;

    private $form;

    public function __construct(array $data = array())
    {
        if (isset($data['value'])) {
            $this->entity = $data['value'];
        }

        if (isset($data['entity'])) {
            $this->entity = $data['entity'];
        }

        if (isset($data['form'])) {
            $this->form = $data['form'];
        }

        unset($data);
    }

    /**
     * @return string
     */
    public function getEntity()
    {
        return $this->entity;
    }

    /**
     * @return string
     */
    public function getForm()
    {
        return $this->form;
    }
}
