# Tree trait

Документация в процессе разработки. Некоторые примеры могут быть нерабочими.

Пример использования:

```php
class Category
{
    use TreeTrait;
}

$category = new Category();

// добавление child
$subcategory1 = new Category();
$category->addChildNode($subcategory1);

// добавление child после конкретного child
$subcategory2 = new Category();
$category->addChildNode($subcategory2);
$subcategory3 = new Category();
$category->addChildNodeAfter($subcategory3, $subcategory1);

// удаление child
$category->removeChildNode($subcategory2);

// получение parent
$parent = $subcategory1->getParentNode();

// замена ноды
$betterCategory3 = new Category();
$subcategory3->replaceNodeObject($betterCategory3);

// проход по всем потомкам рекурсивно
foreach ($category->walk() as $level => $item) {
    echo str_repeat('.', $level) . " " . $item->name . "\n";
}

// проход по всем потомкам рекурсивно наоборот (от конечных элементов к корням) 
foreach ($category->walkRecursive() as $level => $item) {
    echo str_repeat('.', $level) . " " . $item->name . "\n";
}

// проход по всем родителям
foreach ($subcategory->walkUp() as $parent) {
    echo $parent->name . "\n";
}

```
