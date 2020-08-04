<?php

namespace craftnet\composer\vcs;

use Composer\Semver\Comparator;
use Craft;
use craft\helpers\Json;
use craftnet\composer\PackageRelease;
use craftnet\errors\VcsException;
use Github\Client;
use Github\Exception\RuntimeException;
use Github\ResultPager;
use yii\base\InvalidArgumentException;

/**
 * @property array $versions
 */
class GitHub extends BaseVcs
{
    /**
     * @var Client The GitHub API client to use
     */
    public $client;

    /**
     * @var string
     */
    public $owner;

    /**
     * @var string
     */
    public $repo;

    /**
     * @return array
     * @throws VcsException
     */
    public function getVersions(): array
    {
        $versions = [];

        $api = $this->client->repos();
        $paginator = new ResultPager($this->client);
        try {
            $tags = $paginator->fetchAll($api, 'tags', [$this->owner, $this->repo]);
        } catch (RuntimeException $e) {
            throw new VcsException($e->getMessage(), $e->getCode(), $e);
        }

        foreach ($tags as $tag) {
            $name = $this->cleanTag($tag['name']);

            // Special case for Craft CMS - avoid any versions before the 3.0 Beta
            if ($this->package->name === 'craftcms/cms' && Comparator::lessThan($name, '3.0.0-beta.1')) {
                continue;
            }

            $versions[$name] = $tag['commit']['sha'];
        }

        return $versions;
    }

    /**
     * @inheritdoc
     */
    public function getChangelogUrl()
    {
        if (($plugin = $this->package->getPlugin()) && $plugin->changelogPath) {
            return rtrim($this->package->repository, '/') . '/blob/HEAD/' . $plugin->changelogPath;
        }
        return null;
    }

    /**
     * @param PackageRelease $release
     */
    public function populateRelease(PackageRelease $release)
    {
        // Get the composer.json contents
        $api = $this->client->repos();
        try {
            $response = $api->contents()->show($this->owner, $this->repo, 'composer.json', $release->sha);
        } catch (RuntimeException $e) {
            Craft::warning("Ignoring package version {$this->package->name}:{$release->version} due to error loading composer.json: {$e->getMessage()}", __METHOD__);
            Craft::$app->getErrorHandler()->logException($e);
            $release->invalidate("error loading composer.json: {$e->getMessage()}");
            return;
        }

        try {
            $config = Json::decode(base64_decode($response['content']));
        } catch (InvalidArgumentException $e) {
            Craft::warning("Ignoring package version {$this->package->name}:{$release->version} due to error decoding composer.json: {$e->getMessage()}", __METHOD__);
            Craft::$app->getErrorHandler()->logException($e);
            $release->invalidate("error decoding composer.json: {$e->getMessage()}");
            return;
        }

        if ($this->populateReleaseFromComposerConfig($release, $config) === false) {
            return;
        }

        if ($release->time === null) {
            try {
                $commit = $api->commits()->show($this->owner, $this->repo, $release->sha);
                $release->time = $commit['commit']['committer']['date'];
            } catch (RuntimeException $e) {
                Craft::warning("Couldn't determine the release time for {$this->package->name}:{$release->version} due to error loading commit info: {$e->getMessage()}", __METHOD__);
                Craft::$app->getErrorHandler()->logException($e);
            }
        }

        $release->dist = [
            'type' => 'zip',
            'url' => "https://api.github.com/repos/{$this->owner}/{$this->repo}/zipball/{$release->sha}",
            'reference' => $release->sha,
            'shasum' => '',
        ];

        // Get the changelog contents if this is Craft or a plugin
        if ($this->package->name === 'craftcms/cms') {
            $changelogPath = 'CHANGELOG.md';
        } else if ($plugin = $this->package->getPlugin()) {
            $changelogPath = $plugin->changelogPath;
        } else {
            $changelogPath = null;
        }

        if ($changelogPath) {
            try {
                $response = $api->contents()->show($this->owner, $this->repo, $changelogPath, $release->sha);
                $release->changelog = base64_decode($response['content']);
            } catch (RuntimeException $e) {
                Craft::warning("Couldn't fetch changelog for {$this->package->name}:{$release->version} due to error loading {$changelogPath}: {$e->getMessage()}", __METHOD__);
                Craft::$app->getErrorHandler()->logException($e);
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function createWebhook()
    {
        $api = $this->client->repos();

        $params = [
            'name' => 'web',
            'events' => ['create', 'push', 'release', 'delete'],
            'active' => true,
            'config' => [
                'url' => 'https://api.craftcms.com/webhook/github',
                'content_type' => 'json',
                'secret' => $this->package->webhookSecret,
            ],
        ];

        try {
            $info = $api->hooks()->create($this->owner, $this->repo, $params);
        } catch (RuntimeException $e) {
            throw new VcsException($e->getMessage(), $e->getCode(), $e);
        }

        $this->package->webhookId = $info['id'];
    }

    /**
     * @inheritdoc
     */
    public function deleteWebhook()
    {
        $api = $this->client->repos();

        try {
            $api->hooks()->remove($this->owner, $this->repo, $this->package->webhookId);
        } catch (RuntimeException $e) {
            throw new VcsException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
