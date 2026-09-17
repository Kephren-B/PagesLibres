<?php

declare(strict_types=1);

namespace App\Doctrine;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;

use function sprintf;

/**
 * UNACCENT(chaîne) — rend la fonction `unaccent` de PostgreSQL accessible au DQL.
 *
 * Le DQL ne connaît que ses propres fonctions : sans cet enregistrement,
 * `unaccent(...)` provoquerait une erreur de syntaxe. Il est branché dans
 * `config/packages/doctrine.yaml` (`orm.dql.string_functions`).
 *
 * Elle sert à la recherche par titre et par auteur (F8) : « etranger » doit
 * trouver « L'Étranger », et « miserables » doit trouver « Les Misérables ».
 * PostgreSQL sait le faire nativement, à condition d'appeler `unaccent` des
 * **deux** côtés du LIKE — c'est ce que fait le filtre RechercheTexteFilter.
 *
 * ⚠️ `unaccent()` est déclarée STABLE par PostgreSQL, jamais IMMUTABLE : elle ne
 * peut donc pas alimenter un index d'expression, et cette recherche parcourt la
 * table. Acceptable sur un catalogue de cette taille ; à plus grande échelle, il
 * faudrait une colonne normalisée, remplie à l'écriture.
 */
final class FonctionUnaccent extends FunctionNode
{
    public Node $chaine;

    public function parse(Parser $parser): void
    {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);

        $this->chaine = $parser->StringPrimary();

        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }

    public function getSql(SqlWalker $sqlWalker): string
    {
        return sprintf('unaccent(%s)', $sqlWalker->walkSimpleArithmeticExpression($this->chaine));
    }
}
