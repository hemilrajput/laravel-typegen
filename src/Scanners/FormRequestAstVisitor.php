<?php

declare(strict_types=1);

namespace Hemilrajput\TypeGen\Scanners;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

class FormRequestAstVisitor extends NodeVisitorAbstract
{
    public ?Node\Expr\Array_ $rulesReturn = null;

    public function enterNode(Node $node)
    {
        if ($node instanceof Node\Stmt\ClassMethod && $node->name->toString() === 'rules') {
            foreach ($node->stmts ?? [] as $stmt) {
                if ($stmt instanceof Node\Stmt\Return_ && $stmt->expr instanceof Node\Expr\Array_) {
                    $this->rulesReturn = $stmt->expr;

                    return NodeTraverser::DONT_TRAVERSE_CHILDREN;
                }
            }
        }

        return null;
    }
}
