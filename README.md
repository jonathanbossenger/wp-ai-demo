# WordPress AI Demo

A demonstration plugin for WordPress AI integration featuring an interactive chat interface and OpenAI API proxy.

## Features

- **Interactive Chat Interface**: Modern React-based chat UI that appears in WordPress admin
- **OpenAI API Integration**: Secure proxy for OpenAI API calls with authentication
- **WordPress Feature Integration**: Optional integration with WordPress Feature API for enhanced functionality
- **Conversation Persistence**: Automatic conversation history saving in browser localStorage
- **Admin Settings**: Easy configuration of OpenAI API keys through WordPress admin

## Installation

1. Upload the plugin to your `wp-content/plugins/` directory
2. Activate the plugin through the WordPress admin 'Plugins' menu
3. Go to `Settings > WordPress AI Demo` to configure your OpenAI API key
4. The chat interface will appear in your WordPress admin area

## Configuration

### OpenAI API Key Setup

1. Navigate to `Settings > WordPress AI Demo` in your WordPress admin
2. Enter your OpenAI API key
3. Save the settings

### Optional: WordPress Feature API Integration

For enhanced functionality, install the WordPress Feature API plugin. The AI Demo will automatically detect and integrate with it to provide additional WordPress-specific tools and features.

## Usage

Once configured, a chat interface will appear in your WordPress admin area. You can:

- Chat with AI about your WordPress site
- Ask questions about your content, plugins, and settings
- Get assistance with WordPress-related tasks
- Use natural language to interact with your site

## API Endpoints

The plugin provides the following REST API endpoints:

- `GET /wp/v2/ai-demo-proxy/v1/healthcheck` - Check API configuration status
- `GET /wp/v2/ai-demo-proxy/v1/models` - List available OpenAI models
- `POST /wp/v2/ai-demo-proxy/v1/chat/completions` - Proxy OpenAI chat completions

## Development

### Building the Frontend

```bash
npm install
npm run build
```

### Development Mode

```bash
npm run start
```

### Project Structure

```
wp-ai-demo/
├── wp-ai-demo.php              # Main plugin file
├── includes/                   # PHP classes
│   ├── class-wp-ai-api-proxy.php
│   ├── class-wp-ai-api-options.php
│   └── class-wp-feature-register.php
├── src/                        # React/TypeScript source
│   ├── agent/                  # AI agent orchestration
│   ├── components/             # React components
│   ├── context/                # React context providers
│   ├── hooks/                  # Custom React hooks
│   └── types/                  # TypeScript type definitions
├── build/                      # Compiled assets (auto-generated)
├── package.json
├── tsconfig.json
└── webpack.config.js
```

## Security

- All API endpoints require `manage_options` capability
- OpenAI API keys are stored securely in WordPress options
- All API communications are proxied through WordPress for security

## Requirements

- WordPress 6.0 or higher
- PHP 7.4 or higher
- OpenAI API key

## License

GPL-2.0-or-later