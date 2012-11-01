Demo
-----

URL : [http://kalyanchakravarthy.net/projects/tada-notes-v0.2rc](http://kalyanchakravarthy.net/projects/tada-notes-v0.2rc)

Admin password : tada

About
-----

Tada notes is a simple note authoring tool, with facility for custom page templates.

Page creation is as simple as opening one of these urls 

 - [./edit:page/to/create.html](./edit:page/to/create.html). 
 - [./page/to/create.html?edit](./page/to/create.html?edit)

Content will essentially consist of a page title and page content, which will be placed inside a custom template.
Content is authored/rendered using the [markdown syntax](http://daringfireball.net/projects/markdown/syntax)
To edit this page you will have to use this [./edit:index.html](./edit:index.html)

Tada notes is written in `php` and all data is stored in an `sqlite database` in the form of key value pairs. The piece of software, after build, consists of 3 files. `.htaccess`, `index.php`, `notes.data.sqlite`, `markdown.php`.

Page Templates
-------------

A template is similar to a page, but it starts with a dot '.', and doesn't have title. And default template is named as .template.html

A page such as /hello/world/foo.html, will use a template file in this priority (high to low) which ever is existing first

1. /hello/world/.foo.html (page specific template)
2. /hello/world/.template.html (template for same directory order)
3. /hello/.template.html (parent directory order)
4. /.template.html (root)

This page uses [./.template.html](./.template.html) as the default. So it can be edited from here [./edit:.template.html](./edit:.template.html)

Or you can also create a page specific template from this [edit:.tada-notes.html](edit:tada-notes.html)

Template Variables
----------------
Tada notes also support simple template operations such as includes, template variables, etc

### Template variables ###
To display page content, any template will have to use these following two template variables (without spacing within square brackets ofcourse)

1. `[ [ tpl:title ] ]`
2. `[ [tpl:content] ]`

One can also set additional variables other than those default vars by using this syntax,  

    [ [ set foo:hello world whats up man? ] ]

And they can be displayed using,  

    [ [ tpl:foo ] ]

### Includes ###
Any other page can be included within another page by using the following operator,  

    [ [ include:page/to/include.html ] ]

Currently includes are performed only within in a page and not in template (yep, i know its dumb)

### Extra ###
By default all page content is parsed using Markdown parser. If you don't want your text to be parsed you may use the tpl-static operator

    [ [ tpl-static ] ]

Changelog
-------------
 - (v0.2) - 23 Oct 2012 - Refined the project to have a build process
 - (v0.1) - 19 Oct 2010 - First draft for personal use

 