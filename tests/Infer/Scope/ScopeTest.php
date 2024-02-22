<?php

use Dedoc\Scramble\Infer\Analyzer\ClassAnalyzer;

function getStatementTypeForScopeTest(string $statement, array $extensions = [])
{
    return analyzeFile('<?php', $extensions)->getExpressionType($statement);
}

it('infers property fetch nodes types', function ($code, $expectedTypeString) {
    expect(getStatementTypeForScopeTest($code)->toString())->toBe($expectedTypeString);
})->with([
    ['$foo->bar', 'unknown'],
    ['$foo->bar->{"baz"}', 'unknown'],
]);

it('infers static property fetch nodes types', function ($code, $expectedTypeString) {
    expect(getStatementType($code)->toString())->toBe($expectedTypeString);
})->with([
    ['parent::$bar', 'unknown'],
]);

it('infers concat string type', function ($code, $expectedTypeString) {
    expect(getStatementTypeForScopeTest($code)->toString())->toBe($expectedTypeString);
})->with([
    ['"a"."b"."c"', 'string(string(a), string(b), string(c))'],
]);
it('infers concat string type with unknowns', function ($code, $expectedTypeString) {
    expect(getStatementTypeForScopeTest($code)->toString())->toBe($expectedTypeString);
})->with([
    ['"a"."b".auth()->user()->id', 'string(string(a), string(b), unknown)'],
]);

it('analyzes call type of param properly', function () {
    $foo = app(ClassAnalyzer::class)
        ->analyze(ScopeTest_Foo::class)
        ->getMethodDefinition('foo');

    expect($foo->type->getReturnType()->toString())->toBe('int(42)');
});
class ScopeTest_Foo
{
    public function foo(ScopeTest_Bar $bar)
    {
        return $bar->getAnswer();
    }
}
class ScopeTest_Bar
{
    public function getAnswer()
    {
        return 42;
    }
}
