# Microsub

- Contributors: pfefferle, indieweb
- Donate link: https://notiz.blog/donate/
- Tags: microsub, indieweb, reader, feeds, rss
- Requires at least: 6.2
- Tested up to: 6.9
- Stable tag: 1.0.0
- Requires PHP: 7.4
- License: GPL-2.0-or-later
- License URI: https://www.gnu.org/licenses/gpl-2.0.html

A Microsub server reference implementation for WordPress.

## Description

[Microsub](https://indieweb.org/Microsub-spec) is a standardized API for creating and managing feeds. It separates feed reading clients from feed aggregation servers, allowing users to use any compatible client with any compatible server.

This plugin implements the server side of the Microsub specification. It provides a flexible adapter system that allows reader plugins to integrate with the Microsub API.

### Features

- Full Microsub API implementation
- Multi-adapter support with result aggregation
- Automatic endpoint discovery via HTML and HTTP headers
- IndieAuth integration for authentication

### Requirements

- [IndieAuth](https://wordpress.org/plugins/indieauth/) plugin for authentication
- A reader plugin that provides a Microsub adapter

## Frequently Asked Questions

### What is Microsub?

Microsub is a standardized API for creating and managing feeds. It separates the feed reading interface (client) from the feed aggregation and storage (server), similar to how IMAP separates email clients from email servers.

### How do I use this plugin?

This plugin provides the Microsub API endpoint. You need:

1. The [IndieAuth plugin](https://wordpress.org/plugins/indieauth/) for authentication
2. A reader plugin that provides a Microsub adapter (like the Friends plugin)
3. A Microsub client (like Monocle, Indigenous, or Together)

### Where is the Microsub endpoint?

The endpoint is available at `/wp-json/microsub/1.0/endpoint`. Discovery is automatically added to your site's HTML `<head>` and HTTP headers.

### How do I create a custom adapter?

See the [adapter documentation](docs/adapters.md) for a complete guide on creating custom adapters.

### How do I set up a local development environment?

See the [development documentation](docs/development.md) for setup instructions, testing, and code quality tools.

### What scopes are required?

- `read` - Required for timeline, search, and preview actions
- `channels` - Required for channel management
- `follow` - Required for follow/unfollow actions
- `mute` - Required for mute/unmute actions
- `block` - Required for block/unblock actions

## Changelog

### 1.0.0

- Initial release
- Full Microsub API implementation
- Multi-adapter support
- Friends plugin adapter included

## Installation

Follow the normal instructions for [installing WordPress plugins](https://developer.wordpress.org/advanced-administration/plugins/installing-plugins/).

### Automatic Plugin Installation

1. Go to Plugins > Add New
2. Search for "microsub"
3. Click Install Now
4. Click Activate

### Manual Plugin Installation

1. Download the plugin from [GitHub](https://github.com/pfefferle/wordpress-microsub)
2. Upload the `microsub` folder to `/wp-content/plugins/`
3. Activate the plugin through the Plugins menu

## Upgrade Notice

### 1.0.0

Initial release.
