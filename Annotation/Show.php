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
 * @Target({"METHOD"})
 *
 * @author Muhammad Surya Ihsanuddin <surya.kejawen@gmail.com>
 */
class Show
{
    /** @var  array */
    private $groups;

    public function __construct($options)
    {
        $this->groups = $options['groups'];
    }

    public function getGroups() {

        return $this->groups;
    }
}
