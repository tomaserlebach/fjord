<?php

namespace AwStudio\Fjord\Application\Vue;

use Exception;
use Illuminate\View\View;
use AwStudio\Fjord\Application\Application;

class VueApplication
{
    /**
     * Props that are passed to the vue application.
     * 
     * @var array
     */
    protected $props = [];

    /**
     * Fjord application instance.
     * 
     * @var AwStudio\Fjord\Application\Application
     */
    protected $app;

    /**
     * Component instance.
     * 
     * @var \AwStudio\Fjord\Application\Vue\Component
     */
    protected $component;

    /**
     * Determines if the application has been build.
     * 
     * @var bool
     */
    protected $hasBeenBuild = false;

    /**
     * Required props that need to be passed to fjord::app view.
     *
     * @var array
     */
    protected $required = [
        'component',
    ];

    /**
     * Compiler for root props.
     *
     * @var array
     */
    protected $compiler = [
        'model' => Props\ModelProp::class
    ];

    /**
     * Create new VueApplication instance.
     *
     * @param \AwStudio\Fjord\Application\Application $app
     * @return void
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Build Vue application.
     * 
     * @param Illuminate\View\View $view
     * @return void
     */
    public function build(View $view)
    {
        if ($view->getName() != "fjord::app") {
            throw new Exception('Fjord application can only be build for view "fjord::app".');
        }

        $this->setDefaultProps();

        $this->setPropsFromViewData($view->getData());

        $this->compileRootProps();

        $this->initializeComponent($this->props['component']);

        $this->hasBeenBuild = true;
    }

    /**
     * Default props for Fjord Vue application are defined here.
     *
     * @return void
     */
    protected function setDefaultProps()
    {
        $this->props = [
            'config' => collect(config('fjord')),
            'auth' => fjord_user(),
            'app-locale' => $this->app->get('translator')->getLocale(),
            'translatable' => collect([
                'language' => app()->getLocale(),
                'languages' => collect(config('translatable.locales')),
                'fallback_locale' => config('translatable.fallback_locale'),
            ]),
        ];
    }

    /**
     * Execute extensions for the given components.
     * 
     * @param Illuminate\View\View $view
     * @param array $extensions
     * @return void
     * 
     * @throws \Exception
     */
    public function extend(View $view, array $extensions)
    {
        if (!$this->hasBeenBuild()) {
            throw new Exception('Fjord Vue application cannot be extended if it has not been build.');
        }

        if (!$this->component) {
            return;
        }

        foreach ($extensions as $extension) {

            // Look for extensions for the current component.
            if ($this->component->getName() != $extension['component']) {
                continue;
            }

            $this->executeExtension(
                new $extension['extension']()
            );
        }
    }

    /**
     * Execute extension for component if user has permission.
     *
     * @param $extension
     * @return void
     */
    protected function executeExtension($extension)
    {
        if (!$extension->authenticate(fjord_user())) {
            return;
        }

        $extension->handle(
            $this->component
        );
    }

    /**
     * Initialize component class for the given vue component.
     * 
     * @var string $component
     */
    protected function initializeComponent(string $component)
    {
        foreach ($this->app->get('packages')->all() as $package) {
            $components = $package->getComponents();
            foreach ($components as $name => $class) {
                if ($name != $component) {
                    continue;
                }

                $this->component = new $class($component, $this->props['props'] ?? []);
                return;
            }
        }
    }

    /**
     * Merge view data into props.
     *
     * @param array $data
     * @return void
     */
    protected function setPropsFromViewData(array $data)
    {
        $this->checkForRequiredProps($data);

        foreach ($data as $name => $value) {

            // Do not overwrite default props.
            if ($this->propExists($name)) {
                continue;
            }

            $this->props[$name] = $value;
        }
    }

    /**
     * Checks if prop exists.
     * 
     * @param string $name
     * @return boolean
     */
    protected function propExists(string $name)
    {
        return array_key_exists($name, $this->props);
    }

    /**
     * Run compiler for matching root props.
     *
     * @return void
     */
    protected function compileRootProps()
    {
        foreach ($this->compiler as $prop => $compiler) {
            if (!$this->propExists($prop)) {
                continue;
            }

            $instance = with(new $compiler(
                $this->props[$prop]
            ));

            $this->props[$prop] = $instance->getValue();
        }
    }

    /**
     * Check if all required props are passed to view.
     *
     * @param array $data
     * @return void
     * 
     * @throws \Exception
     */
    protected function checkForRequiredProps($data)
    {
        foreach ($this->required as $name) {
            if (!array_key_exists($name, $data)) {
                throw new Exception("Missing required variable \"{$name}\" for view fjord::app.");
            }
        }
    }

    /**
     * Get props for Fjord Vue application.
     *
     * @return array $props
     */
    public function props()
    {
        if ($this->component) {
            $this->props['props'] = $this->component->getProps();
        }

        return $this->props;
    }

    /**
     * Checks if Fjord Vue application has been build. 
     *
     * @return boolean
     */
    protected function hasBeenBuild()
    {
        return $this->hasBeenBuild;
    }
}
