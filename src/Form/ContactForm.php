<?php

declare(strict_types=1);

namespace App\Form;

use WilyCode\Filters\TrimFilter;
use WilyCode\Form\Form;
use WilyCode\FormCsrf\CsrfTokenField;
use WilyCode\FormCsrf\CsrfTokenManagerInterface;
use WilyCode\Validators\EmailValidator;
use WilyCode\Validators\StringLengthValidator;
use WilyCode\Validators\StringValidator;

final class ContactForm extends Form
{
    public function __construct(private readonly CsrfTokenManagerInterface $csrfTokens)
    {
        parent::__construct();
    }

    protected function init(): void
    {
        $this->addDefaultFieldFilter(new TrimFilter());
        $this->add(new CsrfTokenField($this->csrfTokens, 'contact'));

        $this->addField('name')
            ->addValidator(new StringValidator())
            ->addValidator(new StringLengthValidator()->setMin(2)->setMax(120));

        $this->addField('email')
            ->addValidator(new StringValidator())
            ->addValidator(new EmailValidator())
            ->addValidator(new StringLengthValidator()->setMax(254));

        $this->addField('message')
            ->addValidator(new StringValidator())
            ->addValidator(new StringLengthValidator()->setMin(10)->setMax(5000));
    }
}
