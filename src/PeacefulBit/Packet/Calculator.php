<?php

namespace PeacefulBit\Packet;

use PeacefulBit\Packet\Parser\Tokenizer;
use PeacefulBit\Packet\Visitors\NodeCalculatorVisitor;
use PeacefulBit\Packet\Context\Context;

class Calculator
{
    private $rootContext;

    public function __construct(array $native = [])
    {
        $this->rootContext = new Context($native);
    }

    public function calculate($code)
    {
        $tokenizer = new Tokenizer;
        $visitor = new NodeCalculatorVisitor($this->rootContext);
        $tokens = $tokenizer->tokenize($code);
        $tree = $tokenizer->deflate($tokens);
        $node = $tokenizer->convertSequenceToNode($tree);
        return $visitor->visit($node);
    }
}