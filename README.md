# wsuwp-plugin-people-api
Add a custom API endpoint for retrieving profiles from the people directory.

## URL Parameters to Filter the Response (optional)


| Tables                  | Are                                                                             |
| ----------------------- | ------------------------------------------------------------------------------- |
| count                   | Number of results per page. 'All' returns all profiles. Defaults to 10.         |
| page                    | Integer representing the page of results to return                              |
| nid                     | Comma delimited list of people network ids                                      |
| university-category     | Comma delimited list of wsuwp_university_category taxonomy slugs                |
| university-location     | Comma delimited list of wsuwp_university_location taxonomy slugs                |
| university-organization | Comma delimited list of wsuwp_university_org taxonomy slugs                     |
| size                    | Photo size (thumbnail, medium, medium_large, large, full).  Defaults to medium. |
