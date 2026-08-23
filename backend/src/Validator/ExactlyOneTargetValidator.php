<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class ExactlyOneTargetValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ExactlyOneTarget) {
            throw new UnexpectedTypeException($constraint, ExactlyOneTarget::class);
        }

        if ($value === null) {
            return;
        }

        $accessor = PropertyAccess::createPropertyAccessor();
        $filled = 0;

        foreach ($constraint->fields as $field) {
            if ($accessor->getValue($value, $field) !== null) {
                $filled++;
            }
        }

        if ($filled !== 1) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ fields }}', implode(', ', $constraint->fields))
                ->addViolation();
        }
    }
}
