<?php

namespace App\Subscriber;

use App\Service\LogService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\TerminalEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class LogSubscriber implements EventSubscriberInterface
{
  private LogService $logService;

  public function __construct(LogService $logService)
  {
    $this->logService = $logService;
  }

  public static function getSubscribedEvents()
  {
    return [
      KernelEvents::TERMINATE => ['requestLog'],
    ];
  }

  public function requestLog(TerminateEvent $event)
  {
    $response = $event->getResponse();

    $this->logService->updateLogResponse($response, null, true, true);
  }
}
