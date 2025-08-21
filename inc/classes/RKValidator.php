<?php
namespace inc\classes;

require_once __DIR__ . '/../../config.php';

/**
 * Extends the original validator class and register new validation rules.
 * @author Thimira Dilshan <thimirad865@gmail.com>
 * @link https://white-moss-03c58b010.2.azurestaticapps.net/
 */

use Rakit\Validation\Validator;
use inc\classes\ValidatorRules\UniqueRule;
use inc\classes\ValidatorRules\ExistsRule;
use inc\classes\ValidatorRules\StringRule;

class RKValidator extends Validator
{
    public function __construct(array $translations = [])
    {
        parent::__construct($translations);

        // Register your custom rules here
        $this->addValidator('unique', new UniqueRule());
        $this->addValidator('exists', new ExistsRule());
        $this->addValidator('string', new StringRule());

        // You can also set global aliases or other config here if needed
    }
}