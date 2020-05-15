# Elasticsearch Helper Preview

`elasticsearch_helper_preview` is a module that allows editors to preview content in a front-end application in decoupled Drupal projects.

By default this module supports `node` entity type but can be customized to work with any content entity type.

## Requirements
- Elasticsearch Helper module
- Front-end application which contains an end-point where content is displayed if Elasticsearch index name, document ID and type is given (e.g., `/preview/[index_name]/[id]/[type]`).

## Usage

### Usage in Drupal

Add front-end application base URL and temporary preview index expiration time on the settings 
page (`/admin/config/search/elasticsearch_helper/preview`). 

Add `preview` property to the Elasticsearch index plugin definition which supports the entity type of your choice. For example:

```
"preview" => [
  "default" => [
    "path" => "/preview/{_index}/{_id}/{_type}",
  ],
]
```

Placeholders in preview `path` field will be replaced with the values in `_source` field of the Elasticsearch document.
Additionally, the following placeholders can be used:

- `{_index}` - name of the temporary index where preview is stored.
- `{_id}` - ID of the Elasticsearch document where entity is indexed.
- `{_type}` - type of the Elasticsearch document.

Order of placeholders is arbitrary. 

If preview is enabled for node content types, the `Preview` button will be used for content preview in the front-end application. Default Drupal preview functionality will be disabled. 

### Usage in front-end application

Your front-end application should be able to read an Elasticsearch document from specified index when user is redirected to the preview page, e.g, `/preview/preview-index-0bfcd590-f173-4655-a951/101/_doc`.

## Example

Let's assume that the front-end application runs on `http://localhost:4000` and preview index expiration time is set to 3 minutes.

The front-end application has an end-point `/[page-id]` which displays the content of an Elasticsearch document
returned as a result of a query in `page` index based on provided page ID in the `id` field.

The end-point `/[page-id]` also takes a query parameter `?index_name=[index_name]` which allows overriding the default
index name where query by page ID is performed.

In Drupal Elasticsearch index plugin which indexes the page nodes are as follows:
```
/**
 * Page index plugin.
 *
 * @ElasticsearchIndex(
 *   id = "page",
 *   label = @Translation("Page"),
 *   indexName = "page",
 *   typeName = "_doc",
 *   entityType = "node",
 *   bundle = "page",
 *   preview = {
 *     "default" => {
 *       "path" => "/{id}?index_name={_index}",
 *     },
 *   },
 * )
 */
class PageIndex extends ElasticsearchIndexBase {
}
```

When editor previews the node by clicking on the `Preview` button, the node will be indexed in a temporary index and a
modal window will be opened pointing to `/[node-ID]?index_name=[temporary-index-name]`. At this point front-end 
application end-point will read the contents from a temporary preview index.  

After 3 minutes cron will remove the temporary preview index as part of garbage collection.
