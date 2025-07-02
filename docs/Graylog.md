# Graylog Integration

This document explains how to use the Graylog integration in the phpList core application.

## Overview

Graylog is a log management platform that collects, indexes, and analyzes log messages from various sources. The phpList core application is configured to send logs to Graylog using the GELF (Graylog Extended Log Format) protocol.

## Configuration

The Graylog integration is configured in the following files:

- `config/config_prod.yml` - Production environment configuration
- `config/config_dev.yml` - Development environment configuration

### Default Configuration

By default, the application is configured to:

- In production: Send logs of level "error" and above to Graylog
- In development: Send logs of all levels to Graylog

The default configuration points to a placeholder Graylog server at `graylog.example.com:12201`. You need to update this to point to your actual Graylog server.

### Updating the Graylog Server Details

To update the Graylog server details, modify the following sections in the configuration files:

In `config/config_prod.yml`:

```yaml
graylog:
    type: gelf
    publisher:
        hostname: graylog.example.com # Replace with your Graylog server hostname
        port: 12201 # Default GELF UDP port
    level: error # Only send errors and above to Graylog
```

In `config/config_dev.yml`:

```yaml
graylog:
    type: gelf
    publisher:
        hostname: graylog.example.com # Replace with your Graylog server hostname
        port: 12201 # Default GELF UDP port
    level: debug # Send all logs to Graylog in development
    channels: ['!event']
```

Replace `graylog.example.com` with the hostname or IP address of your Graylog server, and update the port if necessary.

## Graylog Server Setup

To receive logs from the application, your Graylog server needs to be configured with a GELF UDP input:

1. In the Graylog web interface, go to System > Inputs
2. Select "GELF UDP" from the dropdown and click "Launch new input"
3. Configure the input with the following settings:
   - Title: phpList Core
   - Bind address: 0.0.0.0 (to listen on all interfaces)
   - Port: 12201 (or the port you specified in the configuration)
4. Click "Save"

## Testing the Integration

To test if logs are being sent to Graylog:

1. Generate some log messages in the application (e.g., by triggering an error)
2. Check the Graylog web interface to see if the logs are being received
3. If logs are not appearing, check the application logs for any errors related to the Graylog connection

## Troubleshooting

If logs are not appearing in Graylog:

1. Verify that the Graylog server is running and accessible from the application server
2. Check that the GELF UDP input is properly configured and running in Graylog
3. Ensure that there are no firewall rules blocking UDP traffic on port 12201 (or your configured port)
4. Check the application logs for any errors related to the Graylog connection
