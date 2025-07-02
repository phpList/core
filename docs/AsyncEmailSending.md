# Asynchronous Email Sending in phpList

This document explains how to use the asynchronous email sending functionality in phpList.

## Overview

phpList now supports sending emails asynchronously using Symfony Messenger. This means that when you send an email, it is queued for delivery rather than being sent immediately. This has several benefits:

1. **Improved Performance**: Your application doesn't have to wait for the email to be sent before continuing execution
2. **Better Reliability**: If the email server is temporarily unavailable, the message remains in the queue and will be retried automatically
3. **Scalability**: You can process the email queue separately from your main application, allowing for better resource management

## Configuration

The asynchronous email functionality is configured in `config/packages/messenger.yaml` and uses the `MESSENGER_TRANSPORT_DSN` environment variable defined in `config/parameters.yml`.

By default, the system uses Doctrine (database) as the transport for queued messages:

```yaml
env(MESSENGER_TRANSPORT_DSN): 'doctrine://default?auto_setup=true'
```

You can change this to use other transports supported by Symfony Messenger, such as:

- **AMQP (RabbitMQ)**: `amqp://guest:guest@localhost:5672/%2f/messages`
- **Redis**: `redis://localhost:6379/messages`
- **In-Memory (for testing)**: `in-memory://`

## Using Asynchronous Email Sending

### Basic Usage

The `EmailService` class now sends emails asynchronously by default:

```php
// This will queue the email for sending
$emailService->sendEmail($email);
```

### Synchronous Sending

If you need to send an email immediately (synchronously), you can use the `sendEmailSync` method:

```php
// This will send the email immediately
$emailService->sendEmailSync($email);
```

### Bulk Emails

For sending to multiple recipients:

```php
// Asynchronous (queued)
$emailService->sendBulkEmail($recipients, $subject, $text, $html);

// Synchronous (immediate)
$emailService->sendBulkEmailSync($recipients, $subject, $text, $html);
```

## Testing Email Sending

You can test the email functionality using the built-in command:

```bash
# Queue an email for asynchronous sending
bin/console app:send-test-email recipient@example.com

# Send an email synchronously (immediately)
bin/console app:send-test-email recipient@example.com --sync
```

## Processing the Email Queue

To process queued emails, you need to run the Symfony Messenger worker:

```bash
bin/console messenger:consume async_email
```

For production environments, it's recommended to run this command as a background service or using a process manager like Supervisor.

## Monitoring

You can monitor the queue status using the following commands:

```bash
# View the number of messages in the queue
bin/console messenger:stats

# View failed messages
bin/console messenger:failed:show
```

## Troubleshooting

If emails are not being sent:

1. Make sure the messenger worker is running
2. Check for failed messages using `bin/console messenger:failed:show`
3. Verify your mailer configuration in `config/parameters.yml`
4. Try sending an email synchronously to test the mailer configuration
