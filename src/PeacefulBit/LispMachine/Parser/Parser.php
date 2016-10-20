<?php

namespace PeacefulBit\LispMachine\Parser;

use PeacefulBit\LispMachine\Lexer;


const TOKEN_OPEN_BRACKET    = '(';
const TOKEN_CLOSE_BRACKET   = ')';
const TOKEN_DOUBLE_QUOTE    = '"';
const TOKEN_BACK_SLASH      = '\\';

const TOKEN_TAB             = "\t";
const TOKEN_SPACE           = " ";
const TOKEN_NEW_LINE        = "\n";
const TOKEN_CARRIAGE_RETURN = "\r";

/**
 * @param $char
 * @return bool
 */
function isStructural($char)
{
    return in_array($char, [
        TOKEN_OPEN_BRACKET,
        TOKEN_CLOSE_BRACKET,
        TOKEN_DOUBLE_QUOTE
    ]);
}

/**
 * @param $char
 * @return bool
 */
function isDelimiter($char)
{
    return in_array($char, [
        TOKEN_TAB,
        TOKEN_CARRIAGE_RETURN,
        TOKEN_NEW_LINE,
        TOKEN_SPACE]
    );
}

/**
 * @param $char
 * @return bool
 */
function isSymbol($char)
{
    return !isDelimiter($char) && !isStructural($char);
}

/**
 * Covert code to list of lexemes using iterative state machine.
 *
 * @param $code
 * @return mixed
 */
function toLexemes($code)
{
    $baseIter = function ($rest, $acc) use (&$baseIter, &$symbolIter, &$stringIter) {
        if (empty($rest)) {
            return $acc;
        }
        $head = $rest[0];
        $tail = substr($rest, 1);
        switch ($head) {
            case TOKEN_OPEN_BRACKET:
                return $baseIter($tail, array_merge($acc, [Lexer\makeLexeme(Lexer\LEXEME_OPEN_BRACKET)]));
            case TOKEN_CLOSE_BRACKET:
                return $baseIter($tail, array_merge($acc, [Lexer\makeLexeme(Lexer\LEXEME_CLOSE_BRACKET)]));
            case TOKEN_DOUBLE_QUOTE:
                return $stringIter($tail, [], $acc);
            default:
                if (isDelimiter($head)) {
                    return $baseIter($tail, $acc);
                }
                return $symbolIter($tail, [$head], $acc);
        }
    };

    $symbolIter = function ($rest, $buffer, $acc) use (&$symbolIter, &$baseIter) {
        if (!empty($rest)) {
            $head = $rest[0];
            $tail = substr($rest, 1);
            if (isSymbol($head)) {
                return $symbolIter($tail, array_merge($buffer, [$head]), $acc);
            }
        }
        $lexeme = Lexer\makeLexeme(Lexer\LEXEME_SYMBOL, $buffer);
        return $baseIter($rest, array_merge($acc, [$lexeme]));
    };

    $stringIter = function ($rest, $buffer, $acc) use (&$stringIter, &$baseIter, &$escapeIter) {
        if (empty($rest)) {
            $bufferString = implode('', $buffer);
            throw new ParserException("Unexpected end of string after \"$bufferString\"");
        }
        $head = $rest[0];
        $tail = substr($rest, 1);
        if ($head == TOKEN_DOUBLE_QUOTE) {
            $lexeme = Lexer\makeLexeme(Lexer\LEXEME_STRING, $buffer);
            return $baseIter($tail, array_merge($acc, [$lexeme]));
        }
        if ($head == '\\') {
            return $escapeIter($tail, $buffer, $acc);
        }
        return $stringIter($tail, array_merge($buffer, [$head]), $acc);
    };

    $escapeIter = function ($rest, $buffer, $acc) use (&$stringIter) {
        if (empty($rest)) {
            $bufferString = implode('', $buffer);
            throw new ParserException("Unused escape character after \"$bufferString\"");
        }
        $head = $rest[0];
        $tail = substr($rest, 1);
        return $stringIter($tail, array_merge($buffer, [$head]), $acc);
    };

    return $baseIter($code, []);
}

/**
 * Convert list of lexemes to ast tree.
 *
 * @param array $lexemes
 * @return array
 */
function toAst($lexemes)
{
    $findCloseBracket = function ($rest, $depth = 0, $offset = 0) use (&$findCloseBracket) {
        if (empty($rest)) {
            return null;
        }
        $head = $rest[0];
        $tail = array_slice($rest, 1);
        switch (Lexer\getType($head)) {
            case Lexer\LEXEME_OPEN_BRACKET:
                return $findCloseBracket($tail, $depth + 1, $offset + 1);
            case Lexer\LEXEME_CLOSE_BRACKET:
                return $depth == 0
                    ? $offset
                    : $findCloseBracket($tail, $depth - 1, $offset + 1);
            default:
                return $findCloseBracket($tail, $depth, $offset + 1);
        }
    };
    $iter = function ($rest, $acc) use (&$iter, &$findCloseBracket) {
        if (empty($rest)) {
            return $acc;
        }
        $head = $rest[0];
        $tail = array_slice($rest, 1);
        if (Lexer\getType($head) == Lexer\LEXEME_OPEN_BRACKET) {
            $closeIndex = $findCloseBracket($tail);
            if (is_null($closeIndex)) {
                throw new ParserException("Unclosed bracket found");
            }
            $expr = toAst(array_slice($tail, 0, $closeIndex));
            $newTail = array_slice($tail, $closeIndex + 1);
            return $iter($newTail, array_merge($acc, [$expr]));
        }
        return $iter($tail, array_merge($acc, [$head]));
    };
    return $iter($lexemes, []);
}