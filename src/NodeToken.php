<?php
/**
 * Created by PhpStorm.
 * User: cronfy
 * Date: 11.09.17
 * Time: 18:17
 */

namespace cronfy\tree;


class NodeToken
{
    public $owner;

    public $meta = [
        'parent' => null,
        'children' => null,
        'firstChild' => null,
        'lastChild' => null,
        'prev' => null,
        'next' => null,
    ];
}