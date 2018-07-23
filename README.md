## AceIDE WordPress Plugin
AceIDE is a fork of the [WPide][1] project.

AceIDE is a WordPress code editor with the long term goal of becoming the ultimate environment to code/develop WordPress themes and plugins. You can edit any files in your wp-content, not just plugins and themes. Code completion will help you remember your WordPress/PHP commands providing function reference along the way. AceIDE allows you to work with multiple files, with basic features such as the tabbed editor, syntax highlighting and line numbers. It also provides some more advanced features such as syntax verification and automatic backups upon saving.

Contributions and feedback is encouraged! If you find an issue, please let us know via the WordPress support forums, or the [GitHub issue tracker][3]. Code contributions are welcomed as a pull request to our GitHub repo.

This plugin would not be possible without the [Ajax.org Cloud9 Editor][4] which is the embedded code editor that powers much of the functionality.

This plugin performs best in the Chrome web browser.

Contributors: Shane Thompson, [WPsites][5], [Thomas Wieczorek][6], X-Raym, [Kevin Young][7]
Tags: code, theme editor, plugin editor, code editor  
Requires at least: 3.0  
Tested up to: 4.9.1  
Stable tag: 2.6.2  

### Current Features:
- Syntax highlighting
- PHP syntax checking before saving to disk to try and banish white screen of death after uploading invalid PHP
- Line numbers
- Find+replace
- Code autocomplete for WordPress and PHP functions along with function description, arguments and return value where applicable
- Colour assist - a colour picker that only shows once you double click a hex colour code in the editor. You can also drag your own image into the colour picker to use instead of the default swatch (see other notes for info).
- Automatic backup of every file you edit. (one daily backup and one hourly backup of each file stored in plugins/AceIDE/backups/filepath)
- File tree allowing you to access and edit any file in your wp-content folder (plugins, themes, uploads etc)
- Use the file browser to rename, delete, download, zip and unzip files (so you can download a zipped version of your whole theme for example)
- Create new files and directories
- Highlight matching parentheses
- Code folding
- Auto indentation
- Tabbed interface for editing multiple files (editing both plugin and theme files at the same time)
- Using the WordPress filesystem API, although currently direct access is forced (edit AceIDE.php in the constructor to change this behaviour) ftp/ssh connections aren't setup yet, since WP will not remember a password need to work out how that will work. Maybe use modal to request password when you save but be able to click save all and save a batch with that password. Passwords defined in wp-config.php are persistent and would fix this problem but people don't generally add those details. Open to ideas here.
- Image editing/drawing
- WordPress Multisite support

### Feature ideas and improvements:
- Improve the code autocomplete command information, providing more information on the commands, adding links through to the WordPress codex and PHP.net website for further info.
- Create an admin panel to choose between syntax highlighting themes and turn on/off other Ajax.org Cloud9 functionality
- Better automated file backup process
- Templates/shortcuts for frequently used code snippets, maybe even with an interface to accept variables that could be injected into code snippet templates.
- Integration with version control systems such as Git

As with most plugins this one is open source. For issue tracking, further information and anyone wishing to get involved and help contribute to this project can do so over on [GitHub][2].  
**Please read CONTRIBUTING.md before submitting a pull request.**

### Installation
1. Run `composer install` to install project dependencies
1. Upload the AceIDE folder to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Access AceIDE by clicking the AceIDE menu item in your main administration menu

### Screenshots
1. Editor view, showing line numbers and syntax highlighting.
1. Image editor in action
1. Showing auto complete, function reference and file tree.
1. Default colour picker image

### Changelog
Please have a look at CHANGELOG.md

### Contributors

Simon Dunton - http://www.wpsites.co.uk  
Thomas Wieczorek - http://www.wieczo.net  
Shane Thompson  
X-Raym
Kevin Young - https://rdytogo.com/

**Please read CONTRIBUTING.md before submitting a pull request.**

  [1]: https://github.com/WPSites/WPide
  [2]: https://github.com/AceIDE/AceIDE
  [3]: https://github.com/AceIDE/AceIDE/issues
  [4]: http://ace.ajax.org
  [5]: http://www.wpsites.co.uk
  [6]: http://www.wieczo.net
  [7]: https://rdytogo.com/
