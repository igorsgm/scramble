<?php

namespace Dedoc\Scramble\Infer\Reflector;

use Dedoc\Scramble\Infer\Services\FileNameResolver;
use Dedoc\Scramble\Infer\Services\FileParser;
use Dedoc\Scramble\Infer\Visitors\PhpDocResolver;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use ReflectionMethod;

class MethodReflector
{
    private static array $cache = [];

    private ?ClassMethod $methodNode = null;

    private function __construct(
        private FileParser $parser, public string $className, public string $name)
    {
    }

    public static function make(string $className, string $name)
    {
        return static::$cache["$className@$name"] = new static(
            app(FileParser::class), // ?
            $className,
            $name,
        );
    }

    public function getMethodCode(ReflectionMethod $reflection = null): string
    {
        $reflection ??= $this->getReflection();

        if ($reflection->isInternal()) {
            return '// Internal method logic here';
        }

        return implode("\n", array_slice(
            preg_split('/\r\n|\r|\n/', file_get_contents($reflection->getFileName())),
            $reflection->getStartLine() - 1,
            $reflection->getStartLine() === $reflection->getEndLine() ? 1 : max($reflection->getEndLine() - $reflection->getStartLine(), 1) + 1,
        ));
    }

    public function getReflection(): ReflectionMethod
    {
        return new ReflectionMethod($this->className, $this->name);
    }

    public function getAstNode(): ClassMethod
    {
        if (! $this->methodNode) {
            $reflection = $this->getReflection();

            // For php internal methods (ie: enum "::cases"), manually construct a ClassMethod node
            if ($reflection->isInternal()) {
                $this->methodNode = new ClassMethod($this->name, [
                    'type' => Node\Stmt\Class_::MODIFIER_PUBLIC | Node\Stmt\Class_::MODIFIER_STATIC,
                    'stmts' => [], // You might want to adjust this according to what you expect
                ]);

                return $this->methodNode;
            }

            $className = class_basename($this->className);

            $methodDoc = $this->getReflection()->getDocComment() ?: '';
            $partialClass = "<?php\nclass $className {\n".$methodDoc."\n".$this->getMethodCode($reflection)."\n}";

            $statements = $this->parser->parseContent($partialClass)->getStatements();
            $node = (new NodeFinder())
                ->findFirst(
                    $statements,
                    fn (Node $node) => $node instanceof Node\Stmt\ClassMethod && $node->name->name === $this->name,
                );

            $traverser = new NodeTraverser;

            $traverser->addVisitor(new class($this->getClassReflector()->getNameContext()) extends NameResolver
            {
                public function __construct($nameContext)
                {
                    parent::__construct();
                    $this->nameContext = $nameContext;
                }

                public function beforeTraverse(array $nodes)
                {
                    return null;
                }
            });
            $traverser->addVisitor(new PhpDocResolver(
                new FileNameResolver($this->getClassReflector()->getNameContext()),
            ));

            $traverser->traverse([$node]);

            $this->methodNode = $node;
        }

        return $this->methodNode;
    }

    public function getClassReflector(): ClassReflector
    {
        return ClassReflector::make($this->className);
    }
}
