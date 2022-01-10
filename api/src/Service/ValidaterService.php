<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Entity;
use App\Entity\Attribute;
use Respect\Validation\Exceptions\NestedValidationException;
use Respect\Validation\Validator as v;
use Respect\Validation\Validator;
use Respect\Validation\Rules;


class ValidaterService
{

    public function __construct(
        EntityManagerInterface $entityManager
    ) {
        $this->entityManager = $entityManager;
    }

    public function validateData(array $data, Entity $entity, string $method)
    {
        // $testValidator = v::numericVal();
        // dump($testValidator->validate('string'));die; // true

        $validator = new Validator;
        // dump(is_int($data['monday']));
        $data['monday'] = 5;
        $validator = $this->addAttributeValidators($validator, $entity, $method);

        try {
            $validator->assert($data);
        } catch(NestedValidationException $exception) {
            return $exception->getMessages();
        }
    }

    private function addAttributeValidators(Validator $validator, Entity $entity, string $method): Validator
    {
        foreach ($entity->getAttributes() as $attribute) {
            $attributeValidator = new Validator;
            $attribute->getType() !== null && $attributeValidator->AddRule($this->getAttTypeRule($attribute->getType()));

            $validator->AddRule(new Rules\Key( $attribute->getName(), $attributeValidator));
        }

        return $validator;
    }

    private function getAttTypeRule($type)
    {
        switch ($type) {
            case 'string':
            case 'text':
                return new Rules\StringType();
                break;
            case 'integer':
            case 'int':
                return new Rules\IntType();
                break;
            case 'float':
                return new Rules\FloatType();
                break;
            case 'number':
                return new Rules\Number();
                break;
            case 'datetime':
                return new Rules\DateTime();
                break;
            case 'file':
                return new Rules\File();
                break;
            case 'object':
                return new Rules\ObjectType();
                break;
            default:
        }
    }

    private function createEntityValidator(array $data, Entity $entity, string $method, Validator $validator)
    {
        // Lets validate each attribute
        foreach ($entity->getAttributes() as $attribute) {
            // fallback for empty data
            !array_key_exists($attribute->getName(), $data) && $data[$attribute->getName()] = null;

            $validator->key($attribute->getName(), $this->createAttributeEntityValidator($data, $attribute, $method, $validator));
// 
            // Lets clean it up
            unset($data[$attribute->getName()]);
        }


        // Lets see if we have attributes that should not be here (if we haven’t cleaned it up it isn’t an attribute)
        foreach ($data as $key => $value) {
            $validator->key(
                $key,
                /** custom not allowed validator*/
            );
        }

        return $validator;
    }



    private function createAttributeEntityValidator(array $data, Attribute $attribute, string $method, Validator $validator)
    {
        // if this is an entity we can skip al this
        if ($attribute->getType() === 'object' || $attribute->getType() === null) {
            // @todo maybe error?
            return $validator;
        }

        // Validate type
        // kijk naar de huidige validations service on validateType()
        

        // Let be a bit compasionate and compatable
        $type = str_replace(['integer', 'boolean', 'text'], ['int', 'bool', 'string'], $attribute->getType());
        // In order not to allow any respect/validation function to be called we explicatly call those containing formats
        $basicTypes = ['bool', 'string', 'int', 'array', 'float'];
        // new route
        if (in_array($type, $basicTypes)) {
            $validator->type($type);
        } else {
            // The are some uncoverd types so we will have to add those manualy
            switch ($type) {
                case 'date':
                    $validator->date();
                    break;
                case 'datetime':
                    $validator->dateTime();
                    break;
                case 'number':
                    $validator->numericVal();
                    break;
                case 'object':
                    // We dont validate an object normaly but hand it over to its own validator
                    $this->validate($data[$attribute->getName()]);
                    break;
                default:
                    // we should never end up here
                    /* @todo throw an custom error */
            }
        }

        // Validate format
        // kijk naar de huidige validations service on validateType()

        // Besides the type and format there could be other validations (like minimal datetime, requered etc)
        foreach ($attribute->getValidations() as $key => $value) {
            switch ($key) {
                    // first we need to do of casses (anything not natively supported by validator or requiring additional logic)
                case 'jsonlogic':
                    // code to be executed if n=label1;
                    break;
                case 'postalcode':
                    // code to be executed if n=label2;
                    break;
                case 'label3':
                    // code to be executed if n=label3;
                    break;
                    // what is then left is the generic stuff
                default:
                    // we should not end up here…
                    // @todo throw error
            }
        }

        return $validator;
    }
}