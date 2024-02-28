<?php

namespace Dedoc\Scramble\Infer\Definition;

use Dedoc\Scramble\Infer\Analyzer\MethodAnalyzer;
use Dedoc\Scramble\Infer\Reflector\MethodReflector;
use Dedoc\Scramble\Infer\Scope\GlobalScope;
use Dedoc\Scramble\Infer\Scope\NodeTypesResolver;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Infer\Scope\ScopeContext;
use Dedoc\Scramble\Infer\Services\FileNameResolver;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\PhpDoc\PhpDocTypeHelper;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeHelper;
use Dedoc\Scramble\Support\Type\TypeWalker;
use Dedoc\Scramble\Support\Type\UnknownType;
use PhpParser\ErrorHandler\Throwing;
use PhpParser\NameContext;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;

class ClassDefinition
{
    private array $methodsScopes = [];
    private array $methodsNodes = [];

    public function __construct(
        // FQ name
        public string $name,
        /** @var TemplateType[] $templateTypes */
        public array $templateTypes = [],
        /** @var array<string, ClassPropertyDefinition> $properties */
        public array $properties = [],
        /** @var array<string, FunctionLikeDefinition> $methods */
        public array $methods = [],
        public ?string $parentFqn = null,
    ) {
    }

    public function isInstanceOf(string $className)
    {
        return is_a($this->name, $className, true);
    }

    public function isChildOf(string $className)
    {
        return $this->isInstanceOf($className) && $this->name !== $className;
    }

    public function getMethodDefinition(string $name, Scope $scope = new GlobalScope): ?FunctionLikeDefinition
    {
        if (! array_key_exists($name, $this->methods)) {
            return null;
        }

        $methodDefinition = $this->methods[$name];

        if (! $methodDefinition->isFullyAnalyzed()) {
            $result = (new MethodAnalyzer(
                $scope->index,
                $this
            ))->analyze($methodDefinition);

            $this->methodsScopes[$name] = $result->scope;
            $this->methodsNodes[$name] = $result->methodNode;
            $this->methods[$name] = $result->definition;
        }

        $methodScope = new Scope(
            $scope->index,
            new NodeTypesResolver,
            new ScopeContext($this, $methodDefinition),
            new FileNameResolver(new NameContext(new Throwing())),
        );

        if (ReferenceTypeResolver::hasResolvableReferences($returnType = $this->methods[$name]->type->getReturnType())) {
            $returnType = (new ReferenceTypeResolver($scope->index))
                ->resolve($methodScope, $returnType)
                ->mergeAttributes($returnType->attributes());

            $this->methods[$name]->type->setReturnType($returnType);
        }

        if ($returnType instanceof UnknownType) {
            $returnType = $this->getMethodCodeReturnType($name) ?? $returnType;

            if ($returnType instanceof UnknownType) {
                // PHP Doc return type is considered only if the code return type is unknown
                $phpDocType = $this->getMethodDocReturnType($name);
                $returnType = $phpDocType ? PhpDocTypeHelper::toType($phpDocType) : $returnType;
            }

            $this->methods[$name]->type->setReturnType($returnType);
        }

        return $this->methods[$name];
    }

    public function getPropertyDefinition($name)
    {
        return $this->properties[$name] ?? null;
    }

    public function getMethodCallType(string $name, ?ObjectType $calledOn = null)
    {
        $methodDefinition = $this->methods[$name] ?? null;

        if (! $methodDefinition) {
            return new UnknownType("Cannot get type of calling method [$name] on object [$this->name]");
        }

        $type = $this->getMethodDefinition($name)->type;

        if (! $calledOn instanceof Generic) {
            return $type->getReturnType();
        }

        return $this->replaceTemplateInType($type, $calledOn->templateTypesMap)->getReturnType();
    }

    /**
     * @TODO: Leverage code of RouteInfo class
     */
    public function getMethodDocReturnType(string $name)
    {
        if (! $phpDoc = $this->methodPhpDoc($name)) {
            return null;
        }

        if (($responseType = $phpDoc->getReturnTagValues('@response')[0] ?? null) && optional($responseType)->type) {
            $responseType->type->setAttribute('source', 'response');

            return $responseType->type;
        }

        if (($returnType = $phpDoc->getReturnTagValues()[0] ?? null) && optional($returnType)->type) {
            return $returnType->type;
        }

        return null;
    }

    /**
     * @TODO: Leverage code of RouteInfo class
     */
    public function methodPhpDoc(string $name): PhpDocNode
    {
        if (!$methodNode = $this->methodsNodes[$name]) {
            return new PhpDocNode([]);
        }

        return $methodNode->getAttribute('parsedPhpDoc') ?: new PhpDocNode([]);
    }

    /**
     * @TODO: Leverage code of RouteInfo class
     */
    public function getMethodCodeReturnType(string $name)
    {
        $reflectionReturnType = MethodReflector::make($this->name, $name)->getReflection()?->getReturnType()?->getName();

        return class_exists($reflectionReturnType)
            ? new ObjectType($reflectionReturnType)
            : TypeHelper::createTypeFromValue($reflectionReturnType);
    }

    private function replaceTemplateInType(Type $type, array $templateTypesMap)
    {
        $type = clone $type;

        foreach ($templateTypesMap as $templateName => $templateValue) {
            (new TypeWalker)->replace(
                $type,
                fn ($t) => $t instanceof TemplateType && $t->name === $templateName ? $templateValue : null
            );
        }

        return $type;
    }

    public function getMethodScope(string $methodName)
    {
        $this->getMethodDefinition($methodName);

        return $this->methodsScopes[$methodName] ?? null;
    }
}
