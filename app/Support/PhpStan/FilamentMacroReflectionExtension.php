<?php

namespace App\Support\PhpStan;

use Closure;
use Filament\Support\Concerns\Macroable;
use Larastan\Larastan\Methods\Macro;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\MethodsClassReflectionExtension;
use PHPStan\Type\ClosureTypeFactory;
use ReflectionException;

/**
 * Larastan's own macro reflection extension only recognises
 * Illuminate\Support\Traits\Macroable. Filament components (Table, Schema,
 * ...) use their own, differently-namespaced Filament\Support\Concerns\Macroable
 * trait, so macros registered on them via ::macro() — such as this repo's
 * Table::socket() from marcusvbda/filament-realtime-driver — are invisible
 * to Larastan without this extension.
 */
class FilamentMacroReflectionExtension implements MethodsClassReflectionExtension
{
    /** @var array<string, MethodReflection> */
    private array $methods = [];

    public function __construct(private ClosureTypeFactory $closureTypeFactory) {}

    public function hasMethod(ClassReflection $classReflection, string $methodName): bool
    {
        if (! in_array(Macroable::class, array_keys($classReflection->getTraits(true)), true)) {
            return false;
        }

        $macro = $this->resolveMacro($classReflection, $methodName);

        if ($macro === null) {
            return false;
        }

        $this->methods[$classReflection->getName().'-'.$methodName] = new Macro(
            $classReflection,
            $methodName,
            $this->closureTypeFactory->fromClosureObject(Closure::fromCallable($macro)),
        );

        return true;
    }

    public function getMethod(ClassReflection $classReflection, string $methodName): MethodReflection
    {
        return $this->methods[$classReflection->getName().'-'.$methodName];
    }

    private function resolveMacro(ClassReflection $classReflection, string $methodName): ?callable
    {
        try {
            $property = $classReflection->getNativeReflection()->getProperty('macros');
        } catch (ReflectionException) {
            return null;
        }

        $property->setAccessible(true);

        /** @var array<string, array<class-string, callable>> $macros */
        $macros = $property->getValue();

        return $macros[$methodName][$classReflection->getName()] ?? null;
    }
}
