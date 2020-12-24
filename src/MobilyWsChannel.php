<?php

namespace NotificationChannels\MobilyWs;

use Illuminate\Events\Dispatcher;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Notification;
use NotificationChannels\MobilyWs\Exceptions\CouldNotSendMobilyWsNotification;

class MobilyWsChannel
{
    /** @var MobilyWsApi */
    private $api;

    /** @var Dispatcher */
    private $events;

    /**
     * MobilyWsChannel constructor.
     *
     * @param MobilyWsApi                   $mobilyWs
     * @param \Illuminate\Events\Dispatcher $events
     */
    public function __construct(MobilyWsApi $mobilyWs, Dispatcher $events)
    {
        $this->api = $mobilyWs;
        $this->events = $events;
    }

    /**
     * Send the given notification.
     *
     * @param mixed        $notifiable
     * @param Notification $notification
     *
     * @return string
     *
     * @throws \NotificationChannels\MobilyWs\Exceptions\CouldNotSendMobilyWsNotification
     */
    public function send($notifiable, Notification $notification)
    {
        if (!method_exists($notification, 'toMobilyWs')) {
            throw CouldNotSendMobilyWsNotification::withErrorMessage('MobilyWs notifications must have toMobilyWs method');
        }
        $message = $notification->toMobilyWs($notifiable, new MobilyWsMessage());
        $number = $notifiable->routeNotificationFor('MobilyWs') ?: $notifiable->phone_number;
        // TODO Validate Number

        $response = $this->dispatchRequest($message, $number);

        if ($response['code'] == 1) {
            return $response['message'];
        }
        $this->events->dispatch(
            new NotificationFailed($notifiable, $notification, 'mobily-ws', $response)
        );

        throw CouldNotSendMobilyWsNotification::mobilyWsRespondedWithAnError($response['code'], $response['message']);
    }

    /**
     * @param $message
     * @param $number
     *
     * @return array
     *
     * @throws CouldNotSendMobilyWsNotification
     */
    private function dispatchRequest($message, $number)
    {
        if (is_string($message)) {
            $response = $this->api->sendString([
                'msg' => $message,
                'numbers' => $number,
            ]);
        } elseif ($message instanceof MobilyWsMessage) {
            $response = $this->api->sendMessage($message, $number);
        } else {
            $errorMessage = sprintf('toMobilyWs must return a string or instance of %s. Instance of %s returned',
                MobilyWsMessage::class,
                gettype($message)
            );
            throw CouldNotSendMobilyWsNotification::withErrorMessage($errorMessage);
        }

        return $response;
    }
}
