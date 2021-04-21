# WPGraphQL Taxonomies

This plugin adds support for querying using custom taxonomies and tax_query to wp-graphql plugin.

## Credits

Initial ideas from [https://github.com/wp-graphql/wp-graphql-tax-query]. And rewritten heavily.

## Activating / Using
Activate the plugin like you would any other WordPress plugin. 

Once the plugin is active, the `taxQuery` argument will be available to any post object connectionQuery 
(posts, pages, custom post types, etc). And this plugin also adds the following arguments to connection queries:

* customTaxonomy
* customTaxonomyId
* customTaxonomyAnd
* customTaxonomyIn
* customTaxonomyNotIn
* customTaxonomySlugAnd
* customTaxonomySlugIn
* customTaxonomySlugNotIn

These work similar to: [https://developer.wordpress.org/reference/classes/wp_query/#tag-parameters]

You can use `taxQuery` along with any of the above arguments - all conditions are `And`ed.

## Example Queries
Below are some example queries. Assume that you have a custom post type `Books`, custom taxonomies
`Book Author` and `Book Format`.


```graphql
# Define a fragment to retrieve bookDetails
fragment bookDetails on RootQueryToBookConnection {
  nodes {
    title
    bookAuthors {
      nodes {
        name
      }
    }
    bookFormats {
      nodes {
        name
      }
    }
  }
}

# Get all books - no tax query
query getBooks {
  books {
    ...bookDetails
  }
}

# Get books by "Steve McConnell"
query getBooks1 {
  books(where: { bookAuthor: "Steve McConnell" }) {
    ...bookDetails
  }
}

# Get books by "Steve McConnell" in Paperback
query getBooks2 {
  books(where: { bookAuthor: "Steve McConnell", bookFormat: "Paperback" }) {
    ...bookDetails
  }
}

# Get books by "Steve McConnell" using taxQuery
query getBooks3 {
  books(
    where: {
      taxQuery: {
        parameters: {
          taxonomy: BOOKAUTHOR
          field: SLUG
          terms: ["Steve McConnell"]
        }
      }
    }
  ) {
    ...bookDetails
  }
}

# Get books by "Steve McConnell" in Paperback using taxQuery
query getBooks4 {
  books(
    where: {
      taxQuery: {
        relation: AND
        relationOperands: [
          {
            parameters: {
              taxonomy: BOOKAUTHOR
              field: SLUG
              terms: ["Steve McConnell"]
            }
          }
          {
            parameters: {
              taxonomy: BOOKFORMAT
              field: SLUG
              terms: ["Paperback"]
            }
          }
        ]
      }
    }
  ) {
    ...bookDetails
  }
}

# Get books by "Steve McConnell" in Paperback using taxQuery and taxonomy argument
query getBooks5 {
  books(
    where: {
      bookFormat: "Paperback"
      taxQuery: {
        parameters: {
          taxonomy: BOOKAUTHOR
          field: SLUG
          terms: ["Steve McConnell"]
        }
      }
    }
  ) {
    ...bookDetails
  }
}

# Get books by "Martin Fowler" or Steve McConnell" in Hardcover using taxQuery
query getBooks6 {
  books(
    where: {
      bookFormatSlugIn: ["Hardcover", "Paperback"]
      taxQuery: {
        relation: OR
        relationOperands: [
          {
            parameters: {
              taxonomy: BOOKAUTHOR
              field: SLUG
              terms: ["Steve McConnell"]
            }
          }
          {
            parameters: {
              taxonomy: BOOKAUTHOR
              field: SLUG
              terms: ["Martin Fowler"]
            }
          }
        ]
      }
    }
  ) {
    ...bookDetails
  }
}
```


The `taxQuery` can be nested to multiple levels.

## Known Issues

* When using with a toplevel taxonomy query - the `taxQuery` argument is ignored. WPGraphQL overwrites the `tax_query` argument for `nav_menu` and `taxonomy` connections. So the following doesn't work:

```graphql
# This doesn't work! - Still retrieves "Hardcover"
query authorBookQuery {
  bookAuthors(where: { name: "Steve McConnell" }) {
    nodes {
      name
      books(where: { bookFormat: "Paperback" }) {
        nodes {
          title
          bookAuthors {
            nodes {
              name
            }
          }
          bookFormats {
            nodes {
              name
            }
          }
        }
      }
    }
  }
}

```

* Not much of error handling. Yet :)
