<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Form\ContactForm;
use App\Model\ContactMessage;
use App\Repository\ContactMessageRepository;
use App\Support\Date;
use App\Support\FlashMessage;
use App\Support\LocaleContext;
use App\Support\Redirector;
use App\Support\ViewRenderer;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use WilyCode\Http\Response\HttpStatus;
use WilyCode\RouterAttribute\Attribute\Get;
use WilyCode\RouterAttribute\Attribute\Post;

final readonly class ContactController
{
    #[Get(path: '/contact', name: 'contact.index')]
    public function index(ViewRenderer $views, ContactForm $form): ResponseInterface
    {
        return $views->render(['form' => $form]);
    }

    #[Post(path: '/contact', name: 'contact.store')]
    public function store(
        ServerRequestInterface $request,
        ContactForm $form,
        ViewRenderer $views,
        ContainerInterface $services,
        FlashMessage $flash,
        Redirector $redirector,
        LocaleContext $locale,
    ): ResponseInterface {
        $form->setRequest($request);

        if (!$form->isValid()) {
            return $views->render(
                ['form' => $form],
                template: 'contact.index',
                status: HttpStatus::UnprocessableContent,
            );
        }

        $message = new ContactMessage($form->getData())
            ->setCreatedAt(Date::now());

        /** @var ContactMessageRepository $contactMessageRepository */
        $contactMessageRepository = $services->get(ContactMessageRepository::class);
        $contactMessageRepository->saveModel($message);

        $flash->success($locale->translator->translate('contact.index', 'success'));

        return $redirector->route('contact.index');
    }
}
