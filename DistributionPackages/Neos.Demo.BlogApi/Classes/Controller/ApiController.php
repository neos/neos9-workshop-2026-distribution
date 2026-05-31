<?php

declare(strict_types=1);

namespace Neos\Demo\BlogApi\Controller;

use GuzzleHttp\Psr7\Uri;
use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Core\Projection\ContentGraph\Filter\FindChildNodesFilter;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAddress;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Http\Helper\UriHelper;
use Neos\Flow\Mvc\Controller\ActionController;
use Neos\Neos\Domain\SubtreeTagging\NeosVisibilityConstraints;
use Neos\Neos\FrontendRouting\NodeUriBuilder;
use Neos\Neos\FrontendRouting\NodeUriBuilderFactory;
use Neos\Neos\FrontendRouting\Options;
use Psr\Http\Message\ResponseInterface;

class ApiController extends ActionController
{
    #[Flow\Inject]
    protected ContentRepositoryRegistry $contentRepositoryRegistry;

    #[Flow\Inject]
    protected NodeUriBuilderFactory $nodeUriBuilderFactory;

    private NodeUriBuilder $nodeUriBuilder;

    public function initializeAction()
    {
        $this->nodeUriBuilder = $this->nodeUriBuilderFactory->forActionRequest($this->request);
    }

    /*
    Alternative using property mapping - bad idea ^^

    public function getPostDetailsAction(?Node $node): ResponseInterface
    {
        if ($node === null) {
            return QueryResponseHelper::clientError(sprintf('Node address %s does not exist in subgraph.' , $this->request->getArgument('node')));
        }
    */

    public function getPostDetailsAction(): ResponseInterface
    {
        $nodeAddress = NodeAddress::fromJsonString($this->request->getArgument('node'));
        $contentRepository = $this->contentRepositoryRegistry->get($nodeAddress->contentRepositoryId);

        // ALTERNATIVE
        // $subgraph = $contentRepository->getContentSubgraph(
        //     workspaceName: $nodeAddress->workspaceName,
        //     dimensionSpacePoint: $nodeAddress->dimensionSpacePoint
        // );

        $subgraph = $contentRepository->getContentGraph(
            workspaceName: $nodeAddress->workspaceName,
        )->getSubgraph(
            dimensionSpacePoint: $nodeAddress->dimensionSpacePoint,
            visibilityConstraints: NeosVisibilityConstraints::excludeDisabled()->merge(NeosVisibilityConstraints::excludeRemoved())
        );

        $nodeInstance = $subgraph->findNodeById($nodeAddress->aggregateId);

        if ($nodeInstance === null) {
            return QueryResponseHelper::clientError(sprintf('Node address %s does not exist in subgraph.' , $nodeAddress->toJson()));
        }

        if ($nodeInstance->nodeTypeName->value !== 'Neos.Demo:Document.BlogPosting') {
            return QueryResponseHelper::clientError(sprintf('Node %s is not a blog posting.' , $nodeAddress->toJson()));
        }

        $uri = $this->nodeUriBuilder->uriFor($nodeAddress, Options::createForceAbsolute());

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

        return QueryResponseHelper::success([
            'title' => $title,
            'abstract' => $abstract,
            'datePublished' => $datePublished,
            'authorName' => $authorName,
            'uri' => $uri,
        ]);
    }

    public function getPostListingAction(NodeAggregateId $blogId, string $language): ResponseInterface
    {
        $contentRepositoryId = ContentRepositoryId::fromString('default');

        $contentRepository = $this->contentRepositoryRegistry->get($contentRepositoryId);

        $subgraph = $contentRepository->getContentSubgraph(WorkspaceName::forLive(), DimensionSpacePoint::fromArray([
            'language' => $language
        ]));

        $blogNode = $subgraph->findNodeById($blogId);
        if ($blogNode === null) {
            return QueryResponseHelper::clientError(sprintf('Blog %s does not exist in subgraph %s %s.' , $blogId->value, $subgraph->getWorkspaceName()->value, $subgraph->getDimensionSpacePoint()->toJson()));
        }

        $baseUri = new Uri($this->uriBuilder->uriFor(actionName: 'getPostDetails', controllerName: 'Api', packageKey: 'Neos.Demo.BlogApi'));


        $postings = [];
        foreach ($subgraph->findChildNodes($blogNode->aggregateId, FindChildNodesFilter::create(nodeTypes: 'Neos.Demo:Document.BlogPosting')) as $blogPositingNode) {
            $postings[] = [
                'id' => $blogPositingNode->aggregateId->value,
                'title' => $blogPositingNode->getProperty('title'),
                'api' => UriHelper::uriWithAdditionalQueryParameters($baseUri, ['node' => NodeAddress::fromNode($blogPositingNode)->toJson()]),
                // ALTERNATIVE 'api' => $baseUri->withQuery(http_build_query(['node' => NodeAddress::fromNode($blogPositingNode)->toJson()]))
            ];
        }

        $blogUri = $this->nodeUriBuilder->uriFor(NodeAddress::fromNode($blogNode), Options::createForceAbsolute());

        return QueryResponseHelper::success([
            'title' => $blogNode->getProperty('title'),
            'postings' => $postings,
            'blogUri' => $blogUri,
        ]);
    }
}
