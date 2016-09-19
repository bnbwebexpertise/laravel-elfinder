<?php namespace Barryvdh\Elfinder;

use Barryvdh\Elfinder\Session\LaravelSession;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Application;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Request;
use League\Flysystem\Cached\CachedAdapter;
use League\Flysystem\Cached\Storage\Memory;
use League\Flysystem\Filesystem;
use Auth;

class ElfinderController extends Controller
{

    protected $package = 'elfinder';

    /**
     * The application instance.
     *
     * @var \Illuminate\Foundation\Application
     */
    protected $app;


    public function __construct(Application $app)
    {
        $this->app = $app;
    }


    public function showIndex()
    {
        return $this->app['view']
            ->make($this->package . '::elfinder')
            ->with($this->getViewVars());
    }


    public function showTinyMCE()
    {
        return $this->app['view']
            ->make($this->package . '::tinymce')
            ->with($this->getViewVars());
    }


    public function showTinyMCE4()
    {
        return $this->app['view']
            ->make($this->package . '::tinymce4')
            ->with($this->getViewVars());
    }


    public function showCKeditor4()
    {
        return $this->app['view']
            ->make($this->package . '::ckeditor4')
            ->with($this->getViewVars());
    }


    public function showPopup($input_id)
    {
        return $this->app['view']
            ->make($this->package . '::standalonepopup')
            ->with($this->getViewVars())
            ->with(compact('input_id'));
    }


    public function showFilePicker($input_id)
    {
        $type = Request::input('type');

        return $this->app['view']
            ->make($this->package . '::filepicker')
            ->with($this->getViewVars())
            ->with(compact('input_id', 'type'));
    }


    public function showConnector()
    {
        $roots = $this->app->config->get('elfinder.roots', []);
        $this->addUserWorkingPath($roots);

        if (empty( $roots )) {
            $dirs = (array) $this->app['config']->get('elfinder.dir', []);

            //$this->addUserWorkingPath($dirs);

            foreach ($dirs as $dir) {
                $roots[] = [
                    'driver'        => 'LocalFileSystem', // driver for accessing file system (REQUIRED)
                    'path'          => public_path($dir), // path to files (REQUIRED)
                    'URL'           => url($dir), // URL to files (REQUIRED)
                    'accessControl' => $this->app->config->get('elfinder.access') // filter callback (OPTIONAL)
                ];
            }

            $disks = (array) $this->app['config']->get('elfinder.disks', []);
            foreach ($disks as $key => $root) {
                if (is_string($root)) {
                    $key  = $root;
                    $root = [];
                }
                $disk = app('filesystem')->disk($key);
                if ($disk instanceof FilesystemAdapter) {
                    $defaults = [
                        'driver'     => 'Flysystem',
                        'filesystem' => $disk->getDriver(),
                        'alias'      => $key,
                    ];
                    $roots[]  = array_merge($defaults, $root);
                }
            }
        }

        if (app()->bound('session.store')) {
            $sessionStore = app('session.store');
            $session      = new LaravelSession($sessionStore);
        } else {
            $session = null;
        }

        $rootOptions = $this->app->config->get('elfinder.root_options', []);
        foreach ($roots as $key => $root) {
            $roots[$key] = array_merge($rootOptions, $root);
        }

        $opts = $this->app->config->get('elfinder.options', []);
        $opts = array_merge($opts, ['roots' => $roots, 'session' => $session]);

        //\Log::debug(print_r($roots, true));

        // run elFinder
        $connector = new Connector(new \elFinder($opts));
        $connector->run();

        return $connector->getResponse();
    }


    protected function getViewVars()
    {
        $dir    = 'packages/barryvdh/' . $this->package;
        $locale = str_replace("-", "_", $this->app->config->get('app.locale'));
        if ( ! file_exists($this->app['path.public'] . "/$dir/js/i18n/elfinder.$locale.js")) {
            $locale = false;
        }
        $csrf = true;

        return compact('dir', 'locale', 'csrf');
    }


    protected function addUserWorkingPath(&$dirs)
    {
        if (Auth::user()) {
            $path = $this->getWorkingPath(Auth::user()->getKey());

            $dirs[] = [
                'driver'        => 'LocalFileSystem',
                'path'          => $path,
                //'startPath'  => 'users/9/',
                'URL'           => env('APP_URL', 'http://localhost') . '/' . $path,
                'alias'         => $path, // set parent to 'LocalVolumes'
                //'tmbCrop'    => false,
                'accessControl' => 'access',
                'attributes'    => [
                    [
                        'pattern' => '/^(.*\/)?\..*/',
                        'read'    => false,
                        'write'   => false,
                        'locked'  => true,
                        'hidden'  => true,
                    ]
                ]
            ];
            //$dirs[] = $this->getWorkingPath(Auth::user()->getKey());
        }
    }


    protected function getWorkingPath($user_id)
    {
        $path = 'users/' . $user_id . '/workspace';
        $dir  = public_path($path);

        if ( ! \File::exists($dir)) {
            \File::makeDirectory($dir, 0775, true);
        }

        return $path;
    }
}
