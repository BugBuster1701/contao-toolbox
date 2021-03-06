<?php


namespace CyberSpectrum\Transifex;

use Guzzle\Http\Client;
use Guzzle\Http\Message\Request;
use Guzzle\Http\Message\Response;
use Guzzle\Plugin\CurlAuth\CurlAuthPlugin;

class Transport
{
	/**
	 * @var \Guzzle\Http\Client
	 */
	protected $client;

	public function __construct($user, $pass)
	{
		$this->client = new Client('http://www.transifex.com/api/2/');
		$this->client->addSubscriber(new CurlAuthPlugin($user, $pass));
	}


	protected function checkError(Response $response, Request $request)
	{
		if (($response->getStatusCode() != 200) && ($response->getStatusCode() != 201))
		{
			switch ($response->getHeader('Content-Type'))
			{
				case 'text/plain':
					throw new \RuntimeException('Error: ' . $response->getBody(true) . ' URI: ' . $request->getUrl());
					break;
				case 'application/json':
					$error = json_decode($response->getBody(true));
					if (isset($error->message))
					{
						throw new \RuntimeException($error->message . ' URI: ' . $request->getUrl());
					}
					break;
				default:
					throw new \RuntimeException('Unknown Error: No error message was returned from the server - Code: ' . $response->getStatusCode() . ' URI: ' . $response->getRequest()->getUrl());
			}
		}
	}

	public function POST($command, $params = null, $postcontenttype = 'application/json')
	{
		$url = $command;

		$headers = array('Content-Type' => $postcontenttype);

		$content = $params;
		if ($postcontenttype == 'application/json')
		{
			$content = json_encode($params);
		}

		$request = $this->client->post($url, $headers, $content);
		try
		{
			/** @var \Guzzle\Http\Message\Response $response */
			$response = $request->send();
			$this->checkError($response, $request);
		}
		catch(\Exception $e)
		{
			$this->checkError($request->getResponse(), $request);
			return null;
		}

		return $response->getBody(true);
	}

	public function PUT($command, $params = null, $postcontenttype = 'application/json')
	{
		$url = $command;

		$headers = array('Content-Type' => $postcontenttype);

		$content = $params;
		if ($postcontenttype == 'application/json')
		{
			$content = json_encode($params);
		}

		$request = $this->client->put($url, $headers, $content);
		try
		{
			/** @var \Guzzle\Http\Message\Response $response */
			$response = $request->send();
			$this->checkError($response, $request);
		}
		catch(\Exception $e)
		{
			$this->checkError($request->getResponse(), $request);
			return null;
		}

		return $response->getBody(true);
	}


	public function execute($command, $params = null)
	{
		$url = $command;

		if (substr($url, -1) !== '/')
		{
			$url .= '/';
		}

		if ($params)
		{
			$p=array();
			foreach ($params as $k => $v)
			{
				if (strlen($v))
				{
					$p[] = urlencode($k) . '=' . urlencode($v);
				}
				else
				{
					$p[] = urlencode($k);
				}
			}
			$url .= '?' . implode('&', $p);
		}

		$request = $this->client->get($url);
		try
		{
			/** @var \Guzzle\Http\Message\Response $response */
			$response = $request->send();
			$this->checkError($response, $request);
		}
		catch(\Exception $e)
		{
			$this->checkError($request->getResponse(), $request);
			return null;
		}

		return $response->getBody(true);
	}

	public function executeJson($command, $params = null, $postdata = null)
	{
		return json_decode($this->execute($command, $params, $postdata), true);
	}
}
