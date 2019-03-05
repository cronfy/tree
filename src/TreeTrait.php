<?php
/**
 * Created by PhpStorm.
 * User: cronfy
 * Date: 12.08.17
 * Time: 1:22
 */

namespace cronfy\tree;

/**
 *
 */
trait TreeTrait
{

    /**
     * @var NodeToken
     */
    protected $_treeNodeToken;

    public function getTreeNodeToken() {
        if (!$this->_treeNodeToken) {
            $this->_treeNodeToken = new NodeToken();
            $this->_treeNodeToken->owner = $this;
        }
        return $this->_treeNodeToken;
    }

    protected $_isRootNode;

    public function getIsRootNode() {
        return (bool) $this->_isRootNode;
    }

    public function setIsRootNode($value) {
        $this->_isRootNode = $value;
    }

    protected $_initChildNodesRequired = true;
    public function initChildNodes() {
        if (!$this->_initChildNodesRequired) return;

        $this->_initChildNodesRequired = false;

        if (!$initializer = $this->getChildNodesInitializer()) return;
        
        $childNodes = $initializer(array_map(
            function ($token) {
                /** @var $token NodeToken */
                return $token->owner;
            },
            $this->getTreeNodeToken()->meta['children'] ?: []
        ));

        $this->getTreeNodeToken()->meta['children'] = [];
        $this->getTreeNodeToken()->meta['firstChild'] = null;
        $this->getTreeNodeToken()->meta['lastChild'] = null;

        foreach ($childNodes as $childNode) {
            /** @var $childNode static */
            $childNode->getTreeNodeToken()->meta['parent'] = null; // чтобы addChildNode() не ругался
            $this->addChildNode($childNode);
        }
    }

    /**
     * Должен проинициализировать childNodes, например, если они берутся из БД.
     * @return callable|void
     */
    protected function getChildNodesInitializer() {}

    protected $_initParentNodeRequired = true;
    public function initParentNode() {
        if (!$this->_initParentNodeRequired) return;

        $this->_initParentNodeRequired = false;

        if ($this->getTreeNodeToken()->meta['parent']) return;

        if (!$initializer = $this->getParentNodeInitializer()) return;

        if (!$parentNode = $initializer()) return;

        /** @var $parentNode static */
        $parentNode->addChildNode($this);
    }

    /**
     * Должен проинициализировать parentNode, например, если он берётся из БД.
     * @return callable|void
     */
    protected function getParentNodeInitializer() {}

    /**
     * @param $child static
     * @throws \Exception
     */
    public function addChildNode($child) {
        // не через getParentNode(), потому что в этот момент
        // может как раз происходить initParentNode()
        if ($child->getTreeNodeToken()->meta['parent']) {
            throw new \Exception("Already has parent");
        }

        // Если этой ноде когда-то делался replaceWith, то у нее может
        // быть сбит владелец (так и задумано в реализации replaceWith()).
        // Проставим правильного владельца.
        // Нельзя просто делать $child->setTreeNodeToken(null) для сброса
        // токена, так как при этом потеряется информация об имеющихся childNodes
        $child->getTreeNodeToken()->owner = $child;

        $newMeta = [
            'child' => [
                'token' => $child->getTreeNodeToken(),
                'meta' => [
                    'parent' => $this->getTreeNodeToken(),
                    'prev' => $this->getTreeNodeToken()->meta['lastChild'],
                    'next' => null,
                ]
            ],
            'parent' => [
                'token' => $this->getTreeNodeToken(),
                'meta' => [
                    'firstChild' => $this->getTreeNodeToken()->meta['firstChild'] ?: $child->getTreeNodeToken(),
                    'lastChild' => $child->getTreeNodeToken(),
                ]
            ],
            'parentOldLastChild' => [
                'token' => $this->getTreeNodeToken()->meta['lastChild'],
                'meta' => [
                    'next' => $child->getTreeNodeToken()
                ],
            ],
        ];
        
        foreach ($newMeta as $name => $member) {
            if (!$marker = $member['token']) continue;

            foreach ($member['meta'] as $key => $value) {
                $marker->meta[$key] = $value;
            }
        }
        
        $this->getTreeNodeToken()->meta['children'][spl_object_hash($child->getTreeNodeToken())] = $child->getTreeNodeToken();
    }

    /**
     * @param $child static
     * @param $after static
     * @throws \Exception
     */
    public function addChildNodeAfter($child, $after) {
        if ($child->getParentNode()) {
            throw new \Exception("Already has a parent");
        }
        if ($after->getParentNode() !== $this) {
            throw new \Exception("Can't add node after foreign child");
        }

        // Если этой ноде когда-то делался replaceWith, то у нее может
        // быть сбит владелец (так и задумано в реализации replaceWith()).
        // Проставим правильного владельца.
        // Нельзя просто делать $child->setTreeNodeToken(null) для сброса
        // токена, так как при этом потеряется информация об имеющихся childNodes
        $child->getTreeNodeToken()->owner = $child;

        $newMeta = [
            'child' => [
                'token' => $child->getTreeNodeToken(),
                'meta' => [
                    'parent' => $this->getTreeNodeToken(),
                    'prev' => $after->getTreeNodeToken(),
                    'next' => $after->getTreeNodeToken()->meta['next'],
                ]
            ],

            'after' => [
                'token' => $after->getTreeNodeToken(),
                'meta' => [
                    'next' => $child->getTreeNodeToken(),
                ],
            ],

            'oldAfterNext' => [
                'token' => $after->getTreeNodeToken()->meta['next'],
                'meta' => [
                    'prev' => $child->getTreeNodeToken(),
                ]
            ],
            
            'parent' => [
                'token' => $this->getTreeNodeToken(),
                'meta' => [
                    'lastChild' => 
                        ($after->getTreeNodeToken() === $this->getTreeNodeToken()->meta['lastChild'])
                        ? $child->getTreeNodeToken()
                        : $this->getTreeNodeToken()->meta['lastChild']
                ]
            ],
        ];

        foreach ($newMeta as $name => $member) {
            if (!$marker = $member['token']) continue;

            foreach ($member['meta'] as $key => $value) {
                $marker->meta[$key] = $value;
            }
        }
        
        $this->getTreeNodeToken()->meta['children'][spl_object_hash($child->getTreeNodeToken())] = $child->getTreeNodeToken();
    }

    /**
     * @param $child static
     * @throws \Exception
     */
    public function removeChildNode($child) {
        if ($child->getParentNode() !== $this) {
            throw new \Exception("Can't remove foreign child");
        }

        $newMeta = [
            'child' => [
                'token' => $child->getTreeNodeToken(),
                'meta' => [
                    'parent' => null,
                    'prev' => null,
                    'next' => null,
                ]
            ],
            'oldPrev' => [
                'token' => $child->getTreeNodeToken()->meta['prev'],
                'meta' => [
                    'next' => $child->getTreeNodeToken()->meta['next'],
                ],
            ],
            'oldNext' => [
                'token' => $child->getTreeNodeToken()->meta['next'],
                'meta' => [
                    'prev' => $child->getTreeNodeToken()->meta['prev'],
                ],
            ],
            'parent' => [
                'token' => $this->getTreeNodeToken(),
                'meta' => [
                    'lastChild' =>
                        ($child->getTreeNodeToken() === $this->getTreeNodeToken()->meta['lastChild'])
                            ? $child->getTreeNodeToken()->meta['prev']
                            : $this->getTreeNodeToken()->meta['lastChild'],
                    'firstChild' =>
                        ($child->getTreeNodeToken() === $this->getTreeNodeToken()->meta['firstChild'])
                            ? $child->getTreeNodeToken()->meta['next']
                            : $this->getTreeNodeToken()->meta['firstChild']
                ]
            ] 
        ];
        
        foreach ($newMeta as $name => $member) {
            if (!$marker = $member['token']) continue;

            foreach ($member['meta'] as $key => $value) {
                $marker->meta[$key] = $value;
            }
        }
        
        unset($this->getTreeNodeToken()->meta['children'][spl_object_hash($child->getTreeNodeToken())]);
    }

    /**
     * @return static
     */
    protected function _getFirstChildNode() {
        $this->initChildNodes();
        return $this->getTreeNodeToken()->meta['firstChild']
            ? $this->getTreeNodeToken()->meta['firstChild']->owner
            : null
        ;
    }

    /**
     * @return static
     */
    public function getNextNode() {
        return $this->getTreeNodeToken()->meta['next']
            ? $this->getTreeNodeToken()->meta['next']->owner
            : null
        ;
    }
    
    /**
     * @return static
     */
    public function getPrevNode() {
        return $this->getTreeNodeToken()->meta['prev']
            ? $this->getTreeNodeToken()->meta['prev']->owner
            : null
        ;
    }
    
    /**
     * @return static
     */
    public function getParentNode() {
        $this->initParentNode();

        return $this->getTreeNodeToken()->meta['parent']
            ? $this->getTreeNodeToken()->meta['parent']->owner
            : null
            ;
    }

    /**
     * После replaceWith() позволяет получить объект, который теперь является узлом дерева
     * вместо замененного.
     * 
     * ```
     * function process($node) {
     *     $newNode = ...;
     *     $node->replaceWith($newNode);
     * }
     *
     * $node = ...;
     * process($node); // нода заменена на другую, но в $node старый объект
     * $node = $node->getSelfNode(); // теперь какой надо
     * ```
     * 
     * @return static
     */
    public function getSelfNode() {
        return $this->getTreeNodeToken()->owner;
    }

    /**
     * Должен быть public, для некоторых операций требуется изменение
     * чужих токенов. Метод внутренний, не предполагается использование
     * его извне. Опасно.
     *
     * @internal
     *
     * @param $value NodeToken|null
     */
    public function setTreeNodeToken($value) {
        $this->_treeNodeToken = $value;
    }

    /**
     * Заменяет в дереве текущую ноду на $newNode.
     *
     * @param $newNode static
     */
    public function replaceWith($newNode) {
        // новой ноде даем наш токен, чтобы она стала частью дерева вместо нас
        $newNode->setTreeNodeToken($this->getTreeNodeToken());
        // токен содержит циклическую ссылку на своего владельца, раньше это были мы,
        // а теперь будет новая нода
        $newNode->getTreeNodeToken()->owner = $newNode;
        // новая нода создана и подцеплена к дереву

        // теперь наведем порядок у себя

        // уничтожаем наш старый токен у себя
        $this->_treeNodeToken = null;
        // создаем новый токен и в циклическую ссылку на владельца записываем НОВУЮ ноду.
        // Это нужно, вот для чего. Если у кого-то есть переменная со ссылкой на нас
        // (на эту ноду, $this), то он должен иметь возможность как-то получить информацию
        // о том, что мы были заменены.
        // Поэтому в свой токен мы пропишем ссылку на новую ноду, и он сможет получить
        // новую ноду через getSelfNode()
        $this->getTreeNodeToken()->owner = $newNode;

        // Все получилось довольно сложно, но зато можно однозначно понять, как этим пользоваться.
        // Вот так:
        // Допустим, есть код, который работает с деревом. Берет ноду и передает ее дальше
        // куда-то на обработку. Так вот, этот код должен понимать, что ноде могли сделать
        // replaceWith(). Если для него это важно, то для продолжения работы с НОВОЙ нодой
        // он должен сделать $node = $node->getSelfNode(). Если он хочет работать со старой
        // нодой, или знает, что replaceWith() произойти не может, то можно getSelfNode()
        // не вызывать.

        return;
    }

    public function getHasChildNodes() {
        $this->initChildNodes();
        return !empty($this->getTreeNodeToken()->meta['children']);
    }

    /**
     * @return static[] 
     * не генератор, потому что мы не хотим, чтобы ноды можно было менять/добавлять
     * в процессе обработки. Мы хотим получить конечное количество нод и перебрать их.
     */
    public function getChildNodes() {
        $result = [];
        $current = $this->_getFirstChildNode();

        while ($current) {
            $result[] = $current;
            $current = $current->getNextNode();
        }
        
        return $result;
    }

    /**
     * @param int $level
     * @param callable $filter
     * @return static[]|void
     */
    public function walk($level = 0, $filter = null) {
        $me = $this;
        if (!$this->getIsRootNode()) {
            if ($filter && !$filter($this)) return;

            yield $level => $me;
            // после yield нода может быть обработана и заменена через
            // replaceWith(). Обновим объект.
            $me = $me->getSelfNode();
        }

        $level++;

        $children = $me->getChildNodes();

        foreach ($children as $current) {
            if (!$filter || $filter($current)) {
                foreach ($current->walk($level, $filter)
                         as $return_level => $return_item) {
                    yield $return_level => $return_item;
                }
            }
        }
    }

    public function walkReverse($level = 0, callable $filter = null) {
        if ($filter && !$filter($this)) return;

        $level++;

        /** @var TreeTrait[] $children */
        $children = $this->getChildNodes();

        if ($children) foreach ($children as $child) {
            if ($filter) {
                if (!$filter($child)) continue;
            }
            foreach ($child->walkReverse($level, $filter) as $return_level => $return_item) {
                yield $return_level => $return_item;
            };
        }

        $level--;

        yield $level => $this;
    }

    public function walkUp() {
        $current = $this;

        do {
            yield $current;
            $current = $current->getParentNode();

        } while ($current);
    }

    public function walkDown() {
        $result = [];
        foreach ($this->walkUp() as $parent) {
            array_unshift($result, $parent);
        }
        return $result;
    }












}