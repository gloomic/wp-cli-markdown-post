# A custom WP-CLI command to publish posts with markdown

The commands provided by [WP-CLI](https://make.wordpress.org/cli/handbook/) (a command line tool to manage your [WordPress](https://wordpress.org) site) are not convenient to use. To insert a new post, you need to pass it many arguments, like title, author, category, etc. In this project we implemented a custom WP-CLI command to allow you to publish and update posts through markdown files. You can write the meta fields, like title, author and category (in the [YAML](http://www.yaml.org) part) and the content in a markdown file and just pass it to the command.

>  **Note:**
>
> The custom command itself does not provide parsing for markdown format content for it may have been enabled by some plugin you are using like [Jetpack](https://wordpress.org/plugins/jetpack). Therefore you need to enable markdown feature through any plugin you like to make the content being displayed as proper HTML in the front.

## Getting started

### Prerequisites

**Install WP-CLI tool**

First, download [wp-cli.phar](https://raw.github.com/wp-cli/builds/gh-pages/phar/wp-cli.phar). On Linux using using `wget` or `curl`. Installing it on Linux:

```shell
# Download wp-cli
$ curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
# Make it executable.
$ chmod +x wp-cli.phar
# Rename it for less typing and move it somewhere in your path.
$ sudo mv wp-cli.phar /usr/local/bin/wp
```

On Windows, it is similar.

See [Installing WP-CLI](https://make.wordpress.org/cli/handbook/guides/installing/) for more details.

### Installing

The installing is simple, just some copy operations. You need install Git hooks and WP-CLI commands:

**Install custom WP-CLI commands**

Copy `wp-cli-markdown-post-command.php` to your current using theme and include it in the `functions.php` file on the server. Take care to use different code depending on you are using a normal theme or a child one (name ends with `-child`).

If you are using a normal theme:

```php
// Use below code if you are using a parent theme.
if ( defined( 'WP_CLI' ) && WP_CLI ) {
    require_once( get_parent_theme_file_path() . '/wp-cli-markdown-post-command.php' );
}
```

Or if you are using a child theme:

```php
// Use below code if you are using a child theme.
if ( defined( 'WP_CLI' ) && WP_CLI ) {
    require_once( get_stylesheet_directory() . '/wp-cli-markdown-post-command.php' );
}
```

## Usage

There are three concrete commands to handle posts with markdown: new, create and update.

- new —  New a markdown file that has contained the supported meta fields.
- create — Publish a post with a markdown file.
- update  — Update a post with a markdown file.

A markdown file contains a YAML part to specify meta fields for a post and the content. The meta fields that can be displayed in the YAML part:

- ID, the post ID, used only if you update a post with update command.

- post_title, the post title.
- post_name, the post name, it will be used as the last part of the post's URL.
- post_author, the ID of the author.
- post_type, the post type.
- post_status, values can be publish,
- tags_input, the name of tags.
- post_category, the names of the categories.
- post_excerpt, the post excerpt.
- description, the description for a post. Only set it if you are using [Yoast](https://yoast.com) (a WordPress SEO plugin).

### new

New a markdown file that has contained the supported meta fields.

**Syntax**

```shell
$ new <file-name-without-extension>
```

**Examples**

```shell
$ wp new useful-git-commands
Success: useful-git-commands.md is created!
```

A new markdown file looks like below, it contains a [YAML](http://www.yaml.org) part (wrapped by `---` and `---`) to allow you to set some meta fields and the post content which follows the YAML.

```markdown
---
post_title:
post_name:
post_author:
post_type: post
post_status: publish
post_date: 2021-03-31 06:34:21
post_modified: 2021-03-31 06:34:21
tags_input:
  -
post_category:
  -
description:
---

```

A full example of a post markdown file:

```markdown
---
post_title: Useful Git commands with examples
post_author: 358
post_type: post
post_status: publish
post_date: 2021-03-31 06:34:21
post_modified: 2021-03-31 06:34:21
tags_input:
  - basic
post_category:
  - git
description: "Some useful git commands which cover most of the usage scenarios."
---

Here are some useful commands with examples. These commands cover most of the usage scenarios in your daily use of git. With them, your experience with git will becomes much more easier.

The second paragraph ...
```

### create

Publish a post with a markdown file.

**Syntax**

```shell
$ create <markdown-file>
# --force, if there have been an ID in the markdown file,
# force to republish it with this option
$ create --force <markdown-file>
```

**Examples**

```shell
$ wp create useful-git-commands.md
Success: 256

$ wp create git/git-getting-started.md
Success: 257
```

This command will update the post and update the markdown file by adding an ID to it:

```markdown
---
ID: 256
post_title: Useful Git commands with examples
```

### update

Update a post with a markdown file.

**Syntax**

```shell
$ update <markdown-file>
```

This command just update the content of a post specified by the ID and both the ID and the content are contained in a markdown file.

**Examples**

```shell
$ wp update git/useful-git-commands.md
```

The `git/useful-git-commands.md` file may looks like this:

```markdown
 ---
 ID: 23
 post_title: Useful Git commands
 post_name: useful-git-commands
 post_author: 3
 post_type: post
 post_status: publish
 tags_input:
   - basic
 post_category:
   - git
 description: This post lists ...
 ---

 In this post, ...
```

> **Note:**
>
> It must contain the `ID` meta data of the post.

## Authors

- *Initial work* - [Gloomic](https://github.com/gloomic)

## License

This project is licensed under the [GPLv2](https://www.gnu.org/licenses/gpl-2.0.html) License.

## Contact

Feel free to contact me on [GloomyCorner](https://www.gloomycorner.com/contact/).