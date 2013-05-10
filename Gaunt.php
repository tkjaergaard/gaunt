<?php namespace Gaunt;

class Gaunt {

    /**
     * Instance
     * @var Gaunt\Gaunt
     */
    public static $instance;

    /**
     * All of the available compiler functions.
     *
     * @var array
     */
    protected $compilers = array(
        'Comments',
        'Arrays',
        'Echos',
        'Codes',
        'Openings',
        'Closings',
        'Else',
        'Includes',
        'Partials',
        'Loop',
    );

    /**
     * Array of opening and closing tags for echos.
     *
     * @var array
     */
    protected $contentTags = array('{{', '}}');

    /**
     * Array of opening and closing tags for code blocks.
     *
     * @var array
     */
    protected $codeTags = array('``', '``');

    /**
     * Pattern to match Wordpress include functions
     * @var regex
     */
    protected $partialPattern = '/(?<!\w)(\s*)get_(header|footer|sidebar)\s*\(\s*((?:\"|\')\w*(?:\"|\'))?\s*\)/';

    /**
     * Path to store cached views
     * @var mixed
     */
    protected $cachePath = null;

    /**
     * Set cache path
     */
    public function __construct()
    {
        $this->cachePath = STYLESHEETPATH."/.tmpl_cache/";

        if( !file_exists($this->cachePath) )
            mkdir($this->cachePath, 0777, true);
    }

    /**
     * Compile file and return path to cached file
     * @param  string $path original path to file
     * @return string path to cached file
     */
    protected function make($path)
    {
        $filePath = $this->getCompiledPath($path);

        if( !$this->isCached($path) )
        {
            $contents = $this->compileString( file_get_contents($path) );
            file_put_contents($filePath , $contents);
        }

        return $filePath;
    }

    /**
     * Determine if the file is cached.
     * @param  string $original Path to the original file
     * @return boolean
     */
    protected function isCached($original)
    {
        $last_modified  = filemtime($original);
        $basename       = basename($original);
        $md5            = md5($original);

        $cache          = glob($this->cachePath."{$md5}_{$basename}");

        $cache_modified = ( isset($cache[0]) ? filemtime($cache[0]) : null );

        if( ! (!!$cache_modified) || intval($cache_modified) < $last_modified )
        {
            @unlink($cache[0]);
            return false;
        }

        return true;
    }

    /**
     * Get path to compiled view
     * @param  string $path original path
     * @return string
     */
    protected function getCompiledPath($path)
    {
        $file          = basename( $path );
        $last_modified = filemtime($path);
        $md5           = md5($path);

        return "{$this->cachePath}{$md5}_{$file}";
    }

    /**
     * Compile the given Gaunt template contents.
     *
     * @param  string  $value
     * @return string
     */
    protected function compileString($value)
    {
        foreach ($this->compilers as $compiler)
        {
            $value = $this->{"compile{$compiler}"}($value);
        }

        return $value;
    }

    /**
     * Compile Gaunt comments into valid PHP.
     *
     * @param  string  $value
     * @return string
     */
    protected function compileComments($value)
    {
        $pattern = sprintf('/%s--((.|\s)*?)--%s/', $this->contentTags[0], $this->contentTags[1]);

        return preg_replace($pattern, '<?php /* $1 */ ?>', $value);
    }

    /**
     * Compile Gaunt dot-notation arrays into valid PHP.
     *
     * @param  string  $value
     * @return string
     */
    protected function compileArrays($value)
    {
        $pattern = sprintf('/%s(\s*)(.+?)\.([\w0-9])(\s*)%s/s', $this->contentTags[0], $this->contentTags[1]);
        $replace = array();

        preg_match_all($pattern, $value, $matches);

        foreach( $matches[3] as $key => $dotNotaions )
        {
            $parts = explode('.', $dotNotaions);

            $replace[$key] = $matches[1][$key]."<?php echo ".$matches[2][$key]."['".implode("']['",$parts)."']; ?>".$matches[4][$key];
        }

        return str_replace($matches[0], $replace, $value);
    }

    /**
     * Compile Gaunt echos into valid PHP.
     *
     * @param  string  $value
     * @return string
     */
    protected function compileEchos($value)
    {
        $pattern = sprintf('/%s\s*(.+?)\s*%s/s', $this->contentTags[0], $this->contentTags[1]);

        return preg_replace($pattern, '<?php echo $1; ?>', $value);
    }

    /**
     * Compile code blocks into valid PHP.
     * @param  string $value
     * @return string
     */
    protected function compileCodes($value)
    {
        $pattern = sprintf('/(?<!\w)(\s*)%s(\s*)(.+?)(\s*)%s/s', $this->codeTags[0], $this->codeTags[1]);
        return preg_replace($pattern, '$1<?php $2$3$4?>', $value);
    }

    /**
     * Compile Gaunt structure openings into valid PHP.
     *
     * @param  string  $value
     * @return string
     */
    protected function compileOpenings($value)
    {
        $pattern = '/(?(R)\((?:[^\(\)]|(?R))*\)|(?<!\w)(\s*)@(if|elseif|foreach|for|while)(\s*(?R)+))/';

        return preg_replace($pattern, '$1<?php $2$3: ?>', $value);
    }

    /**
     * Compile Gaunt structure closings into valid PHP.
     *
     * @param  string  $value
     * @return string
     */
    protected function compileClosings($value)
    {
        $pattern = $this->createClosingMatcher('endif|endforeach|endfor|endwhile');

        return preg_replace($pattern, '$1<?php $2; ?>$3', $value);
    }

    /**
     * Compile Gaunt else statements into valid PHP.
     *
     * @param  string  $value
     * @return string
     */
    protected function compileElse($value)
    {
        $pattern = $this->createPlainMatcher('else');

        return preg_replace($pattern, '$1<?php else: ?>$2', $value);
    }

    /**
     * Compile Gaunt include statements into valid PHP.
     *
     * @param  string  $value
     * @return string
     */
    protected function compileIncludes($value)
    {
        $pattern = $this->createOpenMatcher('include');

        $replace = '<?php include("$2"); ?>';

        preg_match_all($pattern, $value, $matches);

        $search = $matches[0];
        $files  = $matches[2];
        $spaces = $matches[1];

        $replace = array();

        foreach( $files as $key => $file )
        {
            $located = $this->locateFile( preg_replace("/[\"\'\s]*/", "", $file) );

            if( !$located )
            {
                $located = $file;
            }

            $replace[$key] = "{$spaces[$key]}<?php ".__CLASS__."::insert('$located', get_defined_vars());?>";
        }

        return str_replace($search, $replace, $value);
    }

    /**
     * Compile Wordpress partials.
     *
     * @param  string  $value
     * @return string
     */
    protected function compilePartials($value)
    {
        $pattern = $this->partialPattern;
        $find    = array();
        $replace = array();

        preg_match_all($pattern, $value, $matches);

        $spaces = $matches[1];

        foreach( array_unique($matches[0]) as $key => $match )
        {
            $file = $this->compileTemplateFiles(trim($match));

            if( !$file )
                continue;

            $space = (isset($spaces[$key]) ? $spaces[$key] : "");

            $find[] = "/(<\?php\s*)?".preg_quote($match)."\;?(\s*\?>)?/";
            $replace[] = "{$space}<?php ".__CLASS__."::insert('{$file}',get_defined_vars()); ?>";
        }

        return preg_replace($find, $replace, $value);
    }

    /**
     * Compile Wordpress The Loop
     * @param  string   $value
     * @return string
     */
    protected function compileLoop($value)
    {
        $pattern = $this->createClosingMatcher('startloop|noposts|endloop');

        $replace = array();

        preg_match_all($pattern, $value, $matches);

        foreach( $matches[2] as $key => $part )
        {
            switch($part)
            {
                case "startloop":
                    $code = "<?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>";
                    break;

                case "noposts":
                    $code = "<?php endwhile; else: ?>";
                    break;

                case "endloop":
                    if( $key > 0 && $matches[2][$key-1] == 'noposts' )
                    {
                        $code = "<?php endif; ?>";
                    }
                    else
                    {
                        $code = "<?php endwhile; endif; ?>";
                    }
                    break;
                default:
                    $code = "";
            }

            $replace[$key] = $matches[1][$key].$code.$matches[3][$key];
        }

        return str_replace($matches[0], $replace, $value);
    }

    /**
     * Find and compile Wordpress partial files
     * @param  string $function Wordpress function to lookup
     * @return string String to cached file
     */
    protected function compileTemplateFiles($function)
    {
        $pattern = $this->partialPattern;

        preg_match_all($pattern,$function,$matches);

        $base = preg_replace("/[\"\'\s]*/", "", $matches[2][0]);
        $slug = trim($matches[3][0]) ? preg_replace("/[^a-zA-Z-_]/", "", $matches[3][0]) : NULL;

        $templates = array();

        if ( $slug )
            $templates[] = "{$base}-{$slug}.php";

        $templates[] = "{$base}.php";

        $file = $this->locateFile($templates);

        return $file;
    }

    /**
     * Locate theme file
     * @param  array $files priority list of files
     * @return string
     */
    protected function locateFile($files)
    {
        $located = '';

        foreach ( (array) $files as $file ) {

            if ( !$file )
                    continue;
            if ( file_exists(STYLESHEETPATH . '/' . $file)) {
                    $located = STYLESHEETPATH . '/' . $file;
                    break;
            } else if ( file_exists(TEMPLATEPATH . '/' . $file) ) {
                    $located = TEMPLATEPATH . '/' . $file;
                    break;
            }

        }

        return $located;
    }

    /**
     * Get the regular expression for a generic Gaunt function.
     *
     * @param  string  $function
     * @return string
     */
    protected function createMatcher($function)
    {
        return '/(?<!\w)(\s*)@'.$function.'(\s*\(.*\))/';
    }

    /**
     * Get the regular expression for a generic Gaunt function.
     *
     * @param  string  $function
     * @return string
     */
    protected function createOpenMatcher($function)
    {
        return '/(?<!\w)(\s*)@'.$function.'\s*\(\s*(.*)\s*\)/';
    }

    /**
     * Get the regular expression for a generic Gaunt function.
     *
     * @param  string  $function
     * @return string
     */
    protected function createClosingMatcher($function)
    {
        return '/(\s*)@('.$function.')(\s*)/';
    }

    /**
     * Create a plain Gaunt matcher.
     *
     * @param  string  $function
     * @return string
     */
    protected function createPlainMatcher($function)
    {
        return '/(?<!\w)(\s*)@'.$function.'(\s*)/';
    }

    /**
     * Sets the content tags used for the compiler.
     *
     * @param  string  $openTag
     * @param  string  $closeTag
     * @param  bool    $escaped
     * @return void
     */
    protected function setContentTags($openTag, $closeTag, $escaped = false)
    {
        $property = ($escaped === true) ? 'escapedTags' : 'contentTags';

        $this->{$property} = array(preg_quote($openTag), preg_quote($closeTag));
    }

    /**
     * Sets the escaped content tags used for the compiler.
     *
     * @param  string  $openTag
     * @param  string  $closeTag
     * @return void
     */
    protected function setEscapedContentTags($openTag, $closeTag)
    {
        $this->setContentTags($openTag, $closeTag, true);
    }

    /**
     * Cache file if necessary and then include it
     * @param  string $path Path to original file
     * @param  array  $vars Defined vars
     * @return void
     */
    protected function insert($path, $vars)
    {
        extract($vars,EXTR_SKIP);
        include( $this->make($path) );
    }

    /**
     * Facade
     * @param  string   $method   method name
     * @return mixed    Path to file or false
     */
    public static function __callStatic($method,$arguments)
    {
        if( !self::$instance )
            self::$instance = new self();

        switch( count($arguments) )
        {
            case 1:
                return self::$instance->$method($arguments[0]);
            case 2:
                return self::$instance->$method($arguments[0],$arguments[1]);
            default:
                throw new \InvalidArgumentException("Invalid number of arguments provided! ".count($arguments)." was given..");
        }
    }

}

class InvalidMethodException extends \Exception {}