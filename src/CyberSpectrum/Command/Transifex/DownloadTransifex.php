<?php

namespace CyberSpectrum\Command\Transifex;

use CyberSpectrum\Transifex\Project;
use CyberSpectrum\Transifex\Resource;
use CyberSpectrum\Translation\Xliff\File;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

class DownloadTransifex extends TransifexBase
{
	protected function configure()
	{
		parent::configure();
		$this->setName('download-transifex');
		$this->setDescription('Download xliff translations from transifex.');

		$this->addOption(
			'mode', 'm',
			InputOption::VALUE_OPTIONAL,
			'Download mode to use (reviewed, translated, default).', 'reviewed'
		);
	}

	protected function initialize(InputInterface $input, OutputInterface $output)
	{

		parent::initialize($input, $output);

		$translationMode = $input->getOption('mode');
		$validModes = array('reviewed', 'translated', 'default');
		if (!in_array($translationMode, $validModes))
		{
			throw new \InvalidArgumentException(sprintf(
				'Invalid translation mode %s specified. Must be one of %s',
				$translationMode,
				implode(', ', $validModes)
			));
		}
	}


	protected function execute(InputInterface $input, OutputInterface $output)
	{
		if (!$this->project)
		{
			$this->writelnAlways($output, '<error>No project set, exiting.</error>');
			return;
		}

		if (!$this->getApi())
		{
			$this->writelnAlways($output, '<error>No API received, exiting.</error>');
			return;
		}

		$translationMode = $input->getOption('mode');

		// HOTFIX: translated actually appears to be "translator"
		if ($translationMode == 'translated')
		{
			$translationMode = 'translator';
		}

		$project = new Project($this->getApi());

		$project->setSlug($this->project);

		$resources = $project->getResources();

		foreach ($resources as $resource)
		{
			/** @var \CyberSpectrum\Transifex\Resource $resource */
			if (substr($resource->getSlug(), 0, strlen($this->prefix)) != $this->prefix)
			{
				$this->writelnVerbose($output, sprintf('Received resource <info>%s</info> is not for this repository, ignored.', $resource->getSlug()));
				continue;
			}
			$this->writeln($output, sprintf('Processing resource <info>%s</info>', $resource->getSlug()));

			$this->writelnVerbose($output, sprintf('Polling languages from resource <info>%s</info>', $resource->getSlug()));
			$resource->fetchDetails();

			$allLanguages = ($input->getArgument('languages') == 'all');

			foreach (array_keys($resource->getAvailableLanguages()) as $code)
			{
				// we are using 2char iso 639-1 in Contao - what a pitty :(
				if (($allLanguages || in_array(substr($code, 0, 2), $this->languages)) && ($code != $this->baselanguage))
				{
					$this->writeln($output, sprintf('Updating language <info>%s</info>', $code));
					// pull it.
					$data = $resource->fetchTranslation($code, $translationMode);
					if ($data)
					{
						$domain = substr($resource->getSlug(), strlen($this->prefix));
						$localfile = $this->txlang. DIRECTORY_SEPARATOR . substr($code, 0, 2) . DIRECTORY_SEPARATOR . $domain . '.xlf';
						$local = new File($localfile);
						if (!file_exists($localfile))
						{
							// set base values.
							$local->setDatatype('php');
							$local->setOriginal($domain);
							$local->setSrclang($this->baselanguage);
							$local->setTgtlang(substr($code, 0, 2));
						}

						$new = new File(null);
						$new->loadXML($data);

						foreach ($new->getKeys() as $key)
						{
							if ($value = $new->getSource($key))
							{
								$local->setSource($key, $value);
								if ($value = $new->getTarget($key))
								{
									$local->setTarget($key, $value);
								}
							}
						}
						foreach (array_diff($new->getKeys(), $local->getKeys()) as $key)
						{
							$this->writeln($output, sprintf('Language key <info>%s</info> seems to be orphaned, please check.', $key));
						}

						if ($local->getKeys())
						{
							if (!is_dir(dirname($localfile)))
							{
								mkdir(dirname($localfile), 0755, true);
							}
							$local->save();
						}
					}
				}
				else
				{
					$this->writelnVerbose($output, sprintf('skipping language <info>%s</info>', $code));
				}
			}
		}
	}
}
