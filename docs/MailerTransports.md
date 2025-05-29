# Using Different Mailer Transports in phpList

This document explains how to use the various mailer transports that are included in the phpList core dependencies:

- Google Mailer (Gmail)
- Amazon SES
- Mailchimp Transactional (Mandrill)
- SendGrid

## Configuration

The phpList core uses Symfony Mailer for sending emails. The mailer transport is configured using the `MAILER_DSN` environment variable, which is defined in `config/parameters.yml`.

## Available Transports

### 1. Google Mailer (Gmail)

To use Gmail as your email transport:

```
# Using Gmail with OAuth (recommended)
MAILER_DSN=gmail://USERNAME:APP_PASSWORD@default

# Using Gmail with SMTP
MAILER_DSN=smtp://USERNAME:PASSWORD@smtp.gmail.com:587
```

Notes:
- Replace `USERNAME` with your Gmail address
- For OAuth setup, follow [Symfony Gmail documentation](https://symfony.com/doc/current/mailer.html#using-gmail-to-send-emails)
- For the SMTP method, you may need to enable "Less secure app access" or use an App Password

### 2. Amazon SES

To use Amazon SES:

```
# Using API credentials
MAILER_DSN=ses://ACCESS_KEY:SECRET_KEY@default?region=REGION

# Using SMTP interface
MAILER_DSN=smtp://USERNAME:PASSWORD@email-smtp.REGION.amazonaws.com:587
```

Notes:
- Replace `ACCESS_KEY` and `SECRET_KEY` with your AWS credentials
- Replace `REGION` with your AWS region (e.g., us-east-1)
- For SMTP, use the credentials generated in the Amazon SES console

### 3. Mailchimp Transactional (Mandrill)

To use Mailchimp Transactional (formerly Mandrill):

```
MAILER_DSN=mandrill://API_KEY@default
```

Notes:
- Replace `API_KEY` with your Mailchimp Transactional API key
- You can find your API key in the Mailchimp Transactional (Mandrill) dashboard

### 4. SendGrid

To use SendGrid:

```
# Using API
MAILER_DSN=sendgrid://API_KEY@default

# Using SMTP
MAILER_DSN=smtp://apikey:API_KEY@smtp.sendgrid.net:587
```

Notes:
- Replace `API_KEY` with your SendGrid API key
- For SMTP, the username is literally "apikey" and the password is your actual API key

## Testing Your Configuration

After setting up your preferred mailer transport, you can test it using the built-in test command:

```bash
bin/console app:send-test-email recipient@example.com
```

## Switching Between Transports

You can easily switch between different mailer transports by changing the `MAILER_DSN` environment variable. This can be done in several ways:

1. Edit the `config/parameters.yml` file directly
2. Set the environment variable in your server configuration
3. Set the environment variable before running a command:
   ```bash
   MAILER_DSN=sendgrid://API_KEY@default bin/console app:send-test-email recipient@example.com
   ```

## Additional Configuration

Some transports may require additional configuration options. Refer to the Symfony documentation for more details:

- [Symfony Mailer Documentation](https://symfony.com/doc/current/mailer.html)
- [Gmail Transport](https://symfony.com/doc/current/mailer.html#using-gmail-to-send-emails)
- [Amazon SES Transport](https://symfony.com/doc/current/mailer.html#using-amazon-ses)
- [Mailchimp Transport](https://symfony.com/doc/current/mailer.html#using-mailchimp)
- [SendGrid Transport](https://symfony.com/doc/current/mailer.html#using-sendgrid)
