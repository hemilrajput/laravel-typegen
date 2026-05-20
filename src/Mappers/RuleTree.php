<?php

namespace hemilrajput\TypeGen\Mappers;

class RuleTree
{
    /**
     * @param  array<string,mixed>  $rules
     * @return array nested tree
     */
    public function build(array $rules): array
    {
        $tree = [];

        foreach ($rules as $key => $rule) {
            $parts = explode('.', $key);
            $this->insert($tree, $parts, $rule);
        }

        return $tree;
    }

    protected function insert(array &$tree, array $parts, mixed $rule): void
    {
        $head = array_shift($parts);

        if (empty($parts)) {
            // Leaf
            $tree[$head]['__rules'] = $rule;

            return;
        }

        if (! isset($tree[$head])) {
            $tree[$head] = [];
        }

        if ($parts[0] === '*') {
            array_shift($parts);
            if (empty($parts)) {
                // tags.* → item rules for the array
                $tree[$head]['__item_rules'] = $rule;

                return;
            }
            // tags.*.name → array of objects
            $tree[$head]['__items'] ??= [];
            $this->insert($tree[$head]['__items'], $parts, $rule);

            return;
        }

        $this->insert($tree[$head], $parts, $rule);
    }
}
