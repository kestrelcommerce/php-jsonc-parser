<?php

declare(strict_types=1);

namespace Kestrel\JsoncParser\Parser;

/**
 * Node types in the JSON AST
 */
enum NodeType: string
{
    case Object = 'object';
    case Array = 'array';
    case Property = 'property';
    case String = 'string';
    case Number = 'number';
    case Boolean = 'boolean';
    case Null = 'null';
}
