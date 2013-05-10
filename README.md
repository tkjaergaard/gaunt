### What is Gaunt?
Gaunt is the simplest template engine written for Wordpress. The engine is heavily inspired and based upon Laravel 4 Blade. Gaunt is not nearly as heavy and feature-rich as dedicated template engines such as Smarty and Twig. But it helps you simplify your theme pages quite a lot.

### Installation
Gaunt is installable through [Composer](https://packagist.org/packages/gaunt/gaunt).
```
"require" : {
     "gaunt/gaunt":"1.0.*"
}
```

Require "vendor/autoload.php" in your functions.php or plugin.php
```
require("vendor/autoload.php");
```

### Docs

#### The loop
```
@startloop
    ``the_title();``
    ``the_content();``
@endloop


@startloop
    ``the_title();``
    ``the_content();``
@noposts
    <p>No posts yet, sorry..</p>
@endloop
```

#### Code blocks
Code blocks is equal to <?php ?>
```
    ``
         $foo = "bar";
         $bar = ["a","b","c"];
    ``
```

#### Echoing data
```
Hello, {{ $user }}.

Hello, {{ $user.name }} // $user['name'];

Hello, {{ $user->name }}

The current UNIX timestamp is {{ time() }}.
```

#### If statements
```
@if (count($posts) === 1)
    I have one post!
@elseif (count($posts) > 1)
    I have multiple posts!
@else
    I don't have any posts!
@endif
```

#### Loops
```
@for ($i = 0; $i < 10; $i++)
    The current value is {{ $i }}
@endfor

@foreach ($posts as $post)
    <p>The title of this post is: {{ $post->post_title }}</p>
@endforeach

@while (true)
    <p>I'm looping forever.</p>
@endwhile
```

#### Including sub-views
```
@include('view.php')

<?php get_header();?>
<?php get_footer();?>
<?php get_sidebar();?>
```

#### Comments
```
{{-- This comment will not be in the rendered HTML --}}
```

### Examples
```
<!doctype html>
<html lang="en">
<head>
    <title>Page Title</title>
</head>
<body>

    @include('header.php')

    @startloop
        ``the_title();``
        ``the_content();``
    @noposts
        <p>Sorry no posts here..</p>
    @endloop

    @include('footer.php')

</body>
</html>
```