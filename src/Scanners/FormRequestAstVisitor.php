<?php

declare(strict_types=1);

namespace Hemilrajput\TypeGen\Scanners;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

class FormRequestAstVisitor extends NodeVisitorAbstract
{
    public ?Node\Expr\Array_ $rulesReturn = null;

    public string $namespace = '';

    /** @var array<string, string> */
    public array $uses = [];

    public function enterNode(Node $node)
    {
        if ($node instanceof Node\Stmt\Namespace_ && $node->name instanceof Node\Name) {
            $this->namespace = $node->name->toString();
        }

        if ($node instanceof Node\Stmt\Use_) {
            foreach ($node->uses as $use) {
                $alias = $use->alias?->toString() ?? $use->name->getLast();
                $this->uses[$alias] = $use->name->toString();
            }
        }

        if ($node instanceof Node\Stmt\GroupUse) {
            $prefix = $node->prefix->toString();
            foreach ($node->uses as $use) {
                $alias = $use->alias?->toString() ?? $use->name->getLast();
                $this->uses[$alias] = $prefix.'\\'.$use->name->toString();
            }
        }

        if ($node instanceof Node\Stmt\ClassMethod && $node->name->toString() === 'rules') {
            $localArrays = [];

            foreach ($node->stmts ?? [] as $stmt) {
                if ($stmt instanceof Node\Stmt\Expression
                    && $stmt->expr instanceof Node\Expr\Assign
                    && $stmt->expr->var instanceof Node\Expr\Variable
                    && is_string($stmt->expr->var->name)
                    && $stmt->expr->expr instanceof Node\Expr\Array_
                ) {
                    $localArrays[$stmt->expr->var->name] = $stmt->expr->expr;
                }

                if ($stmt instanceof Node\Stmt\Return_) {
                    if ($stmt->expr instanceof Node\Expr\Array_) {
                        $this->rulesReturn = $stmt->expr;

                        return NodeTraverser::DONT_TRAVERSE_CHILDREN;
                    }

                    if ($stmt->expr instanceof Node\Expr\Variable
                        && is_string($stmt->expr->name)
                        && isset($localArrays[$stmt->expr->name])
                    ) {
                        $this->rulesReturn = $localArrays[$stmt->expr->name];

                        return NodeTraverser::DONT_TRAVERSE_CHILDREN;
                    }
                }
            }
        }

        return null;
    }
}
