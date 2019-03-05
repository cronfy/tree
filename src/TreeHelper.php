<?php
/**
 * Created by PhpStorm.
 * User: cronfy
 * Date: 14.08.17
 * Time: 20:16
 */

namespace cronfy\tree;

class TreeHelper
{

    protected static function getDefaultDumpFormatter(callable $nameFormatter = null) {
        return function ($node) use ($nameFormatter) {
            /** @var $node TreeTrait */
            $object = is_a($node, TreeNode::class) ? $node->value : $node;
            switch (true) {
                case is_object($object):
                    $desc = get_class($object);
                    if ($nameFormatter) $desc .= "(" . $nameFormatter($node) . ")";
                    break;
                case is_string($object):
                    $desc = (mb_strlen($object) > 50) ? mb_substr($object, 0, 50) . '...' : $object;
                    break;
                default:
                    $desc = gettype($object);
                    break;
            }
            return
                (is_a($node, TreeNode::class) ? ("TreeNode[$desc]") : $desc)
                . " -> " . count($node->getChildNodes())
                ;
        };
    }

    /**
     * @param $root TreeTrait
     * @param callable|null $nameFormatter
     * @param callable|null $formatter
     * @return string
     */
    public static function dump($root, callable $nameFormatter = null, callable $formatter = null) {
        if (!$formatter) $formatter = static::getDefaultDumpFormatter($nameFormatter);

        $result = '';
        foreach ($root->walk() as $level => $node) {
            $result .= str_repeat('  ', $level) . $formatter($node) . "\n";
        }
        return $result;
    }

    /**
     * @param $array
     * @param callable|string $idGetter
     * @param callable $pidGetter
     * @return TreeNode
     * @throws \Exception
     */
    public static function fromArray($array, $idGetter, $pidGetter) {
        $root = new TreeNode();
        $root->setIsRootNode(true);

        if (is_string($idGetter)) {
            $idKey = $pidGetter;
            $idGetter = function ($item) use ($idKey) { return $item[$idKey]; };
        }

        if (is_string($pidGetter)) {
            $pidKey = $pidGetter;
            $pidGetter = function ($item) use ($pidKey) { return $item[$pidKey]; };
        }

        $nodes = [];
        foreach ($array as $item) {
            $itemId = $idGetter($item);
            $node = new TreeNode();
            $node->value = $item;
            $nodes[$itemId] = $node;
        }

        foreach ($nodes as $node) {
            $pid = $pidGetter($node->value);
            if (array_key_exists($pid, $nodes)) {
                $parent = $nodes[$pid];
            } else {
                $parent = $root;
            }

            $parent->addChildNode($node);
        }

        return $root;
    }
}