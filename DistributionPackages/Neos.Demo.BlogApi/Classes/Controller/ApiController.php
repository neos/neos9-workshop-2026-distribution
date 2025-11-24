<?php

declare(strict_types=1);

namespace Neos\Demo\BlogApi\Controller;

use Neos\ContentRepository\Core\Projection\ContentGraph\Node;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAddress;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\Flow\Mvc\Controller\ActionController;
use Neos\Flow\Annotations as Flow;
use Neos\Neos\FrontendRouting\NodeUriBuilderFactory;
use Neos\Neos\FrontendRouting\Options;

class ApiController extends ActionController
{
    #[Flow\Inject()]
    protected ContentRepositoryRegistry $contentRepositoryRegistry;

    #[Flow\Inject()]
    protected NodeUriBuilderFactory $nodeUriBuilderFactory;

    public function getPostDetailsAction(string $node)
    {
        $nodeAddress = NodeAddress::fromJsonString($node);
        $contentRepository = $this->contentRepositoryRegistry->get($nodeAddress->contentRepositoryId);
        $subgraph = $contentRepository->getContentSubgraph(
            workspaceName: $nodeAddress->workspaceName,
            dimensionSpacePoint: $nodeAddress->dimensionSpacePoint
        );
        $nodeInstance = $subgraph->findNodeById($nodeAddress->aggregateId);

        if ($nodeInstance === null) {
            return QueryResponseHelper::clientError(sprintf('Node address "%s" does not exist in subgraph.' , $nodeAddress->toJson()));
        }

        if ($nodeInstance->nodeTypeName->value !== 'Neos.Demo:Document.BlogPosting') {
            return QueryResponseHelper::clientError(sprintf('Node %s is not a blog posting.' , $nodeAddress->toJson()));
        }

        // $nodeTypeManager = $contentRepository->getNodeTypeManager();
        // $nodeType = $nodeTypeManager->getNodeType($nodeInstance->nodeTypeName);
        // if (!$nodeType || !$nodeType->isOfType('Neos.Demo:Document.BlogPosting')) {
        //     return QueryResponseHelper::clientError(sprintf('Node %s is not a blog posting.' , $nodeAddress->toJson()));
        // }

        $title = $nodeInstance->getProperty('title');
        $abstract = $nodeInstance->getProperty('abstract');
        if ($abstract) {
            $abstract = strip_tags($abstract);
        }
        $datePublished = $nodeInstance->getProperty('datePublished');
        if ($datePublished instanceof \DateTimeImmutable) {
            $datePublished = $datePublished->format(\DateTimeImmutable::ATOM);
        }
        $authorName = $nodeInstance->getProperty('authorName');

        $nodeUriBuilder = $this->nodeUriBuilderFactory->forActionRequest($this->request);

        return QueryResponseHelper::success([
            'title' => $title,
            'abstract' => $abstract,
            'datePublished' => $datePublished,
            'authorName' => $authorName,
            'uri' => $nodeUriBuilder->uriFor(NodeAddress::fromNode($nodeInstance), Options::createForceAbsolute()),
        ]);
    }

    public function getPostListingAction()
    {
        // TODO(con25) implement
        return '{}';
    }
}
