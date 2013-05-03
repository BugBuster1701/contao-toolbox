<?php

namespace CyberSpectrum\Command;

use CyberSpectrum\Transifex\Project;
use CyberSpectrum\Transifex\Resource;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UploadTransifex extends TransifexBase
{
	protected function configure()
	{
		parent::configure();
		$this->setName('upload-transifex');
		$this->setDescription('Upload xliff translations to transifex.');
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		if (!($this->project && $this->getApi()))
		{
			$output->writeln('No project set or no API received, exiting.');
			return;
		}

		$project = new Project($this->getApi());

		$project->setSlug($this->project);

		$resources = $project->getResources();

		$files = $this->getAllTxFiles($this->baselanguage);

		foreach ($files as $file => $basename)
		{
			$noext = basename($basename, '.xlf');
			if (array_key_exists($this->prefix . $noext, $resources))
			{
				// already present, update.
				$output->writeln('Update ressource ' . $this->prefix . $noext);
				/** @var \CyberSpectrum\Transifex\Resource $resource */
				$resource = $resources[$this->prefix . $noext];
				$resource->setContent(file_get_contents($file));
				$resource->updateContent();
			}
			else
			{
				$output->writeln('Create new ressource ' . $this->prefix . $noext);
				// upload new.
				$resource = new Resource($this->getApi());
				$resource->setProject($this->project);
				$resource->setSlug($this->prefix . $noext);
				$resource->setName($resource->getSlug());
				$resource->setSourceLanguageCode($this->baselanguage);

				$resource->setContent(file_get_contents($file));

				$resource->create();
			}
		}
	}
}