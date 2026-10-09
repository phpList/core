# Graylog Integration

phpList can send logs to [Graylog](https://graylog.org/) over GELF (Graylog Extended
Log Format) using Monolog's `gelf` handler. The handler ships **disabled by default**
in both environments.

## Enabling it

1. In `config/config_prod.yml`, uncomment the `graylog` handler under `monolog.handlers`.
   It sends `error`-level and above logs, using the `graylog_host` and `graylog_port`
   parameters from `config/parameters.yml` (defaults: `graylog.phplist.local:12201`).
2. In `config/config_dev.yml`, uncomment the `graylog` handler to also log in
   development. It sends every level except the `event` channel.
3. Update `graylog_host` and `graylog_port` in `config/parameters.yml` to point at
   your Graylog server.

```yaml
# config/parameters.yml
parameters:
    graylog_host: 'graylog.example.com'
    graylog_port: 12201
```

## Graylog server setup

Your Graylog server needs a GELF UDP input to receive these logs:

1. In the Graylog web interface, go to System > Inputs.
2. Select "GELF UDP" and click "Launch new input".
3. Configure it with:
   - Title: phpList Core
   - Bind address: `0.0.0.0` (listen on all interfaces)
   - Port: `12201` (or whatever you set as `graylog_port`)
4. Click "Save".

## Testing the integration

1. Trigger a log message in the application (e.g. an error).
2. Check the Graylog web interface for the message.
3. If nothing shows up, see Troubleshooting below.

## Troubleshooting

If logs aren't appearing in Graylog:

1. Confirm the `graylog` handler is uncommented in the config for the environment
   you're testing.
2. Verify the Graylog server is running and reachable from the application server.
3. Check that the GELF UDP input is running and bound to the port you configured.
4. Check for firewall rules blocking UDP traffic on that port.