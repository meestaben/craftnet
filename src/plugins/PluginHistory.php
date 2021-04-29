<?php

namespace craftnet\plugins;

use Craft;
use craft\db\Query;
use craftnet\db\Table;
use yii\base\BaseObject;
use yii\base\NotSupportedException;

class PluginHistory extends BaseObject implements \IteratorAggregate, \ArrayAccess, \Countable
{
    /**
     * @var Plugin
     */
    private $_plugin;

    /**
     * @var array|null
     */
    private $_history;

    /**
     * @param Plugin $plugin
     * @param array $config
     *
     * @inheritdoc
     */
    public function __construct(Plugin $plugin, array $config = [])
    {
        $this->_plugin = $plugin;
        parent::__construct($config);
    }

    /**
     * Adds a new history state.
     *
     * @param string $note
     * @param string|null $devComments
     */
    public function push(string $note, string $devComments = null)
    {
        Craft::$app->getDb()->createCommand()
            ->insert(Table::PLUGINHISTORY, [
                'pluginId' => $this->_plugin->id,
                'note' => $note,
                'devComments' => $devComments,
            ])
            ->execute();

        // Clear the memoized history
        $this->_history = null;
    }

    /**
     * @inheritdoc
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->_getHistory());
    }

    /**
     * @inheritdoc
     */
    public function offsetSet($offset, $value)
    {
        throw new NotSupportedException('Add new plugin history using push().');
    }

    /**
     * @inheritdoc
     */
    public function offsetExists($offset)
    {
        return isset($this->_getHistory()[$offset]);
    }

    /**
     * @inheritdoc
     */
    public function offsetUnset($offset)
    {
        throw new NotSupportedException('Not possible to unset plugin history');
    }

    /**
     * @inheritdoc
     */
    public function offsetGet($offset)
    {
        return $this->_getHistory()[$offset];
    }

    /**
     * @inheritdoc
     */
    public function count()
    {
        return count($this->_getHistory());
    }

    /**
     * Returns the history data.
     *
     * @return array
     */
    private function _getHistory(): array
    {
        if ($this->_history !== null) {
            return $this->_history;
        }

        if (!$this->_plugin->id) {
            return [];
        }

        return $this->_history = (new Query())
            ->select(['note', 'devComments', 'dateCreated'])
            ->from([Table::PLUGINHISTORY])
            ->where(['pluginId' => $this->_plugin->id])
            ->orderBy(['dateCreated' => SORT_DESC])
            ->all();
    }
}
