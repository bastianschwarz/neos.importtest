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

use de\imxnet\foundation\httpclient\iPagedHttpClient;
use de\imxnet\foundation\httpclient\response\pagedResponse\iPagedResponse;
use de\imxnet\imxplatformphp\arrayAccess\ArrayAccess;
use de\imxnet\integration\imxplatform\httpclient\parameter\FindCompactEvents;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Model\NodeTemplate;
use Neos\ContentRepository\Domain\Service\Context;
use Neos\ContentRepository\Domain\Service\ContextFactoryInterface;
use Neos\ContentRepository\Domain\Service\NodeTypeManager;
use Neos\Eel\FlowQuery\FlowQuery;
use Neos\Neos\Utility\NodeUriPathSegmentGenerator;
use Psr\Http\Message\ResponseInterface;

final class Import {

  private ContextFactoryInterface $contextFactory;

  private NodeTypeManager $nodeTypeManager;

  private NodeUriPathSegmentGenerator $nodeUriPathSegmentGenerator;

  private iPagedHttpClient $httpClient;

  private ArrayAccess $arrayAccess;

  public function __construct(iPagedHttpClient            $httpClient,
                              ContextFactoryInterface     $contextFactory,
                              NodeTypeManager             $nodeTypeManager,
                              NodeUriPathSegmentGenerator $nodeUriPathSegmentGenerator,
                              ArrayAccess                 $arrayAccess) {
    $this->contextFactory = $contextFactory;
    $this->nodeTypeManager = $nodeTypeManager;
    $this->nodeUriPathSegmentGenerator = $nodeUriPathSegmentGenerator;
    $this->httpClient = $httpClient;
    $this->arrayAccess = $arrayAccess;
  }

  private function getItemsFromResponse(ResponseInterface $response) : array {
    $json = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
    return $this->arrayAccess->getValue($json, 'compactEvents', []); //@todo this is depending on the request type so this should be a request/parser combination
  }

  public function import() {
    //@todo do we want to support different node types for different request/parser combinations?
    $nodeTypeName = 'Imx.ImxPlatformImport:Document.PlatformContent';

    $nodeTemplate = new NodeTemplate();
    $nodeTemplate->setNodeType($this->nodeTypeManager->getNodeType($nodeTypeName));

    /**
     * @todo id should come from config
     * @todo also the finding of the root node should be done via a node resolver interface with a findByIdentifier as default
     */
    $rootNode = $this->contextFactory->create()->getNodeByIdentifier('c6374fc1-94af-4eab-9117-f655672744f8');
    $rootContexts = array_map(static fn(NodeInterface $rootNodeVariant) : Context => $rootNodeVariant->getContext(), $rootNode->getOtherNodeVariants());

    /**
     * @todo the whole item finding process (building the params, sending the request, parsing the response) should be a item finder interface with a callback/method for each item
     */
    $rootQuery = new FlowQuery([$rootNode]);
    $parameter = new FindCompactEvents(); //@todo add parameter/parser combination
    $pagedResponse = null;
    do {
      $pagedResponse = !$pagedResponse instanceof iPagedResponse ? $this->httpClient->requestFirstPage($parameter) : $this->httpClient->requestNextPage($pagedResponse->getRequestForNextPage());
      foreach($this->getItemsFromResponse($pagedResponse->getResponse()) as $item) { //@todo this could be a callback for a paged client/parser combination
        $entityType = strtolower($item['_entityType']);
        $id = $item['id'];
        $ident = implode('_', [$entityType, $id]);

        $rootQuery->pushOperation('find', [sprintf('[instanceof %s][ident = %s]', $nodeTypeName, $ident)]);
        $defaultNode = $rootQuery->get(0) ?? $rootNode->createNodeFromTemplate($nodeTemplate);

        foreach($this->findNodesToImport($defaultNode, $rootContexts) as $nodeToImport) $this->updateNodeProperties($nodeToImport, $item, $id, $ident, $entityType);
      }
    }while($pagedResponse instanceof iPagedResponse && $pagedResponse->hasNextPage());
  }

  //@todo this looks like another transformer interface
  private function updateNodeProperties(NodeInterface $node, $item, $id, string $ident, string $entityType) : void {
    $nodeLanguage = $node->getDimensions()['language'][0];

    $node->setProperty('title', $item['title'][$nodeLanguage]);
    $node->setProperty('id', $id);
    $node->setProperty('entityType', $entityType);
    $node->setProperty('ident', $ident);
    $node->setProperty('uriPathSegment', $this->nodeUriPathSegmentGenerator->generateUriPathSegment($node));
    $node->setProperty('originalItem', $item);
  }

  //@todo as always, private functions are probably public methods of another interface
  private function findNodesToImport(NodeInterface $baseNode, array $rootContexts) {
    return [$baseNode, ...$baseNode->getOtherNodeVariants(), ...$this->createMissngVariants($rootContexts, $baseNode)];
  }

  /**
   * Creates a diff between the rootContexts (the contexts for all all variants of the rootNode) and the other variants of the current node. The remaining
   * contexts have not yet been imported
   *
   * @param array<Context> $rootVariantContexts The contexts of all variants of the root node
   * @param NodeInterface $node The current node
   * @return array<NodeInterface>
   */
  private function createMissngVariants(array $rootVariantContexts, NodeInterface $node) : array {
    $missingContexts = array_udiff(
      $rootVariantContexts,
      array_map(static fn(NodeInterface $node) : Context => $node->getContext(), $node->getOtherNodeVariants()),
      static fn(Context $leftContext, Context $rightContext) => $leftContext <=> $rightContext
    );
    return array_map(static fn(Context $context) : NodeInterface => $node->createVariantForContext($context), $missingContexts);
  }
}
