<?php


namespace szana8\LaraflowGo\Traits;


use Illuminate\Support\Collection;

trait GoJsAble
{
    /**
     * States attribute for GoJS
     *
     * @var
     */
    protected $laraflowConfiguration;

    /**
     * Initialize node array for GoJS states
     *
     * @var array
     */
    protected $nodeDataArray = [];

    /**
     * Initialize links array for GoJS transitions
     *
     * @var array
     */
    protected $linkDataArray = [];

    /**
     * Return the array of the states which belongs
     * to the proper workflow
     *
     * @return array
     */
    public function getNodeDataArrayAttribute()
    {
        return $this->nodeDataArray;
    }

    /**
     * Load the workflow attributes and transform all of the
     * data to the appropriate format which is use by the
     * GoJS JavaScript diagram engine.
     *
     * @return string
     */
    public function getGoJsObjectAttribute()
    {
        return $this->compile($this->configuration);
    }

    /**
     * Return the default workflow attributes from the Laraflow
     * configuration file.
     *
     * @return \Illuminate\Config\Repository|mixed
     */
    public function getDefaultWorkflow()
    {
        return config('laraflowGo.configuration.default');
    }

    /**
     * Return the default validators array from the Laraflow
     * configuration file.
     *
     * @return \Illuminate\Config\Repository|mixed
     */
    public function getValidatorsAttribute()
    {
        return config('laraflowGo.validators');
    }

    /**
     * Convert the original state array to the appropriate format.
     *
     * @param $configuration
     * @return string
     */
    public function compile($configuration)
    {
        $this->laraflowConfiguration = json_decode($configuration, true);

        return $this->collectGoJs();
    }

    /**
     * Collect all of the necessary attribute for GoJS than
     * cast to json string
     *
     * @return string
     */
    protected function collectGoJs()
    {
        return collect([
            'class' => 'go.GraphLinksModel',
            'linkFromPortIdProperty' => 'fromPort',
            'linkToPortIdProperty' => 'toPort',
            'nodeDataArray' => $this->getNodeDataArray(),
            'linkDataArray' => $this->getLinkDataArray()
        ])->toJson();
    }

    /**
     * Convert the laraflow steps to an array which can
     * acceptable for the GoJS.
     *
     * @return mixed
     */
    protected function getNodeDataArray()
    {
        if(count($this->laraflowConfiguration['steps']) == 0)
            return $this->nodeDataArray;

        collect($this->laraflowConfiguration['steps'])->map(function($value, $key) {
            array_push($this->nodeDataArray, [
                'category' => $value['extra']['category'],
                'text' => $value['text'],
                'key' => $key,
                'loc' => $value['extra']['loc'],
            ]);
        });

        return $this->nodeDataArray;
    }

    /**
     * Collect the transaction links to an array which is acceptable
     * for the GoJS JavaScript diagram engine.
     *
     * @return array    Converted transition array for GoJS
     */
    protected function getLinkDataArray()
    {
        if(count($this->laraflowConfiguration['transitions']) == 0)
            return $this->linkDataArray;

        collect($this->laraflowConfiguration['transitions'])->map(function($value) {
            array_push($this->linkDataArray, [
                'from' => $value['from'],
                'to' => $value['to'],
                'fromPort' => $value['extra']['fromPort'],
                'toPort' => $value['extra']['toPort'],
                'points' => $value['extra']['points'],
                'text' => $value['text'],
                'visible' => true
            ]);
        });

        return $this->linkDataArray;
    }

    /**
     * Convert the front end state format back to the original array what we
     * store in the database
     *
     * @param $configuration
     * @return mixed
     */
    public function decompile($configuration)
    {
        $configurationCollection = json_decode($configuration, true);

        return collect([
            'property_path' => $this->getPropertyPath(),
            'steps' => $this->decompileSteps($configurationCollection),
            'transitions' => $this->decompileLinks($configurationCollection)
        ])->toJson();
    }

    /**
     * Convert the GoJS workflow states to the proper format
     * for the state machine
     *
     * @param $steps
     * @return array
     */
    protected function decompileSteps($steps)
    {
        collect($steps['nodeDataArray'])->map(function($value) {
            array_push($this->nodeDataArray, [
                'text' =>  $value['text'],
                'extra' => [
                    'color' => $value['category'],
                    'category' => $value['category'],
                    'loc' => $value['loc'],
                ]
            ]);
        });

        return  $this->nodeDataArray;
    }

    /**
     * Convert the GoJS workflow links to the proper format
     * for the state machine
     *
     * @param array $links
     * @return array
     */
    protected function decompileLinks(array $links)
    {
        collect($links['linkDataArray'])->map(function ($value) use ($links) {
            array_push($this->linkDataArray, [
                'from' => $this->getStateByKey($links['nodeDataArray'], $value['from']),
                'to' =>  $this->getStateByKey($links['nodeDataArray'], $value['to']),
                'text' => $value['text'],
                'extra' => [
                    'fromPort' => $value['fromPort'],
                    'toPort' => $value['toPort'],
                    'points' => json_encode($value['points'])
                ],
                'callbacks' => [
                    'pre' => [],
                    'post' => []
                ],
                'validators' => isset($value['validators']) ? $value['validators'] : ''
            ]);
        });

        return $this->linkDataArray;
    }

    /**
     * Return the value of the key which belongs to the given
     * state.
     *
     * @param $link
     * @param $searchKey
     * @return int|string
     */
    protected function getStateByKey($link, $searchKey)
    {
        return collect($link)->search(function($value) use ($searchKey) {
            return $value['key'] == $searchKey;
        });
    }

    /**
     * Return the property path value from the original configuration.
     *
     * @return mixed
     */
    protected function getPropertyPath()
    {
        return json_decode($this->configuration, true)['property_path'];
    }
}