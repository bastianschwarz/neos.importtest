<?php declare(strict_types=1);
/**
 * LICENSE
 *
 * This software and its source code is protected by copyright law (Sec. 69a ff. UrhG).
 * It is not allowed to make any kinds of modifications, nor must it be copied,
 * or published without explicit permission. Misuse will lead to persecution.
 *
 * @copyright  2021 infomax websolutions GmbH
 * @link       http://www.infomax-it.de
 */

namespace de\imxnet\neos\imxplatform\import;

use de\imxnet\foundation\httpclient\authentication\BasicAuth;
use de\imxnet\foundation\httpclient\header\link\parser\NextLinksByRegex;
use de\imxnet\foundation\httpclient\iPagedHttpClient;
use de\imxnet\foundation\httpclient\PagedPsrClient;
use de\imxnet\foundation\httpclient\requestFactory\FromPsrFactory;
use de\imxnet\foundation\httpclient\requestFactory\WithAuthentication;
use de\imxnet\foundation\httpclient\requestFactory\WithParametersAsBodyForPostAndQueryStringElse;
use de\imxnet\foundation\httpclient\response\pagedResponse\factory\FromLinkHeader;
use GuzzleHttp\Client;
use Neos\Http\Factories\RequestFactory;

final class ClientFactory {

  public function build(array $settings) : iPagedHttpClient {
    $guzzleClient = new Client(); //the guzzle client that will send the request

    $psrRequestFactory = new RequestFactory();
    $requestFactory =
      new WithParametersAsBodyForPostAndQueryStringElse( //adds the parameter
        new WithAuthentication(new BasicAuth($settings['basicAuth']['user'], $settings['basicAuth']['password']), //adds authentication
          new FromPsrFactory(
            $psrRequestFactory
          )
        )
      );

    return new PagedPsrClient($settings['baseUri'], $guzzleClient, $requestFactory, new FromLinkHeader($psrRequestFactory, new NextLinksByRegex()));
  }
}
