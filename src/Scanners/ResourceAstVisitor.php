<?php

declare(strict_types=1);

namespace Hemilrajput\TypeGen\Scanners;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

class ResourceAstVisitor extends NodeVisitorAbstract
{
    public ?Node\Expr\Array_ $toArrayReturn = null;

    public function enterNode(Node $node)
    {
        if ($node instanceof Node\Stmt\ClassMethod && $node->name->toString() === 'toArray') {
            foreach ($node->stmts ?? [] as $stmt) {
                if ($stmt instanceof Node\Stmt\Return_ && $stmt->expr instanceof Node\Expr\Array_) {
                    $this->toArrayReturn = $stmt->expr;

                    return NodeTraverser::DONT_TRAVERSE_CHILDREN;
                }
            }
        }

        return null;
    }
}
