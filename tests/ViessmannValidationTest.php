<?php

declare(strict_types=1);
include_once __DIR__ . '/stubs/Validator.php';
class BenachrichtigungValidationTest extends TestCaseSymconValidation
{
    public function testValidateBenachrichtigung(): void
    {
        $this->validateLibrary(__DIR__ . '/..');
    }
    public function testValidateBenachrichtigungModule(): void
    {
        $this->validateModule(__DIR__ . '/../Benachrichtigung');
    }
}