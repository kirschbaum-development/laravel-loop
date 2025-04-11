<?php

namespace Kirschbaum\Loop\Tools;

use Exception;
use Stripe\StripeClient;
use Illuminate\Support\Collection;
use Kirschbaum\Loop\Enums\ErrorCode;
use Kirschbaum\Loop\Enums\MessageType;
use Stripe\Exception\ApiErrorException;
use Prism\Prism\Facades\Tool as PrismTool;

class StripeToolkit implements Toolkit
{
    public string $description = "make a call to the stripe api. You can use this tool to fetch any stripe related data.";

    public function __construct(
        public readonly ?string $apiKey = null,
        ?string $description = null,
    ) {
        $this->description = $description ?? $this->description;
    }

    public static function make(...$args): static
    {
        return new self(...$args);
    }

    public function getTools(): Collection
    {
        return collect([
            $this->getApiTool(),
        ]);
    }

    public function getTool(string $name): ?\Prism\Prism\Tool
    {
        return $this->getApiTool();
    }

    public function getApiTool(): \Prism\Prism\Tool
    {
        return PrismTool::as('stripe')
            ->for($this->description)
            ->withStringParameter('method', "HTTP method to use (GET, POST, PUT, DELETE, etc.)", required: true)
            ->withStringParameter('path', "Path to call (e.g. /v1/customers)", required: true)
            // ->withObjectParameter(
            //     name: 'query',
            //     description: "Query parameters to include in the request as a JSON object",
            //     properties: [], // No specific properties needed here, allows any structure
            //     requiredFields: [],
            //     allowAdditionalProperties: true,
            //     required: false // Make query optional
            // )
            ->withStringParameter('body', "HTTP body to use if it is not GET (can be JSON string or other format)", required: false)
            ->withStringParameter('contentType', "HTTP content type to use (default: application/json)", required: false)
            ->using(function (string $method, string $path, ?object $query = null, ?string $body = null, ?string $contentType = null): string {
                if (! class_exists(StripeClient::class)) {
                    return "Error: Stripe SDK not installed. Please install it using 'composer require stripe/stripe-php'.";
                }

                if (! $this->getApiKey()) {
                    return "Error: Stripe SDK not installed. Please install it using 'composer require stripe/stripe-php'.";
                }

                // Initialize Stripe client with API key
                $stripe = new StripeClient(
                    $this->getApiKey()
                );

                // TODO: Refactor this code
                try {
                    $queryData = $query ? json_decode(json_encode($query), true) : [];

                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $queryData = [];
                    }

                    $method = strtoupper($method);
                    $bodyData = null;

                    // Parse body if provided and method is not GET
                    if ($body !== null && !empty($body) && $method !== 'GET') {
                        // Determine content type, default to application/json
                        $effectiveContentType = $contentType ?: 'application/json';

                        // If content type is JSON, try to parse it
                        if (stripos($effectiveContentType, 'application/json') !== false) {
                            if (is_string($body) && str_starts_with(trim($body), '{')) {
                                $bodyData = json_decode($body, true);
                                // If JSON decoding fails, pass the raw string body
                                if (json_last_error() !== JSON_ERROR_NONE) {
                                    $bodyData = $body;
                                }
                            } else {
                                $bodyData = $body; // Pass as-is if not a JSON-like string
                            }
                        } else {
                            // For other content types, pass the body as is
                            $bodyData = $body;
                        }
                    }

                    // Extract resource name and ID from path
                    $path = ltrim($path, '/');

                    // If the path starts with v1/, remove it as Stripe SDK handles API versions
                    if (str_starts_with($path, 'v1/')) {
                        $path = substr($path, 3);
                    }

                    $pathParts = explode('/', $path);
                    $resourceName = $pathParts[0] ?? null;

                    // No resource name found, can't proceed
                    if (!$resourceName) {
                        return "Error: Invalid Stripe API path";
                    }

                    // Handle the request based on the method and path structure
                    switch ($method) {
                        case 'GET':
                            if (count($pathParts) === 1) {
                                // List resource (e.g., /customers)
                                $response = $stripe->{$resourceName}->all($queryData);
                            } else {
                                // Get specific resource (e.g., /customers/{id})
                                $id = $pathParts[1] ?? null;
                                if (!$id) {
                                    return "Error: Resource ID required for GET request";
                                }

                                // Check if this is a nested resource
                                if (count($pathParts) > 2) {
                                    $nestedResource = $pathParts[2] ?? null;
                                    if ($nestedResource) {
                                        // e.g., /customers/{id}/sources
                                        $resource = $stripe->{$resourceName}->retrieve($id);
                                        if (method_exists($resource, $nestedResource)) {
                                            $response = $resource->{$nestedResource}->all($queryData);
                                        } else {
                                            return "Error: Nested resource '$nestedResource' not found for resource '$resourceName'";
                                        }
                                    } else {
                                        $response = $stripe->{$resourceName}->retrieve($id, $queryData);
                                    }
                                } else {
                                    $response = $stripe->{$resourceName}->retrieve($id, $queryData);
                                }
                            }
                            break;

                        case 'POST':
                            if (count($pathParts) === 1) {
                                // Create resource
                                $response = $stripe->{$resourceName}->create($bodyData ?? []);
                            } else {
                                // Update specific resource or call a resource method
                                $id = $pathParts[1] ?? null;
                                if (!$id) {
                                    return "Error: Resource ID required for POST request";
                                }

                                // Check if this is a nested resource or action
                                if (count($pathParts) > 2) {
                                    $action = $pathParts[2] ?? null;
                                    if ($action) {
                                        // Try to call the action method on the resource's API
                                        if (method_exists($stripe->{$resourceName}, $action)) {
                                            $response = $stripe->{$resourceName}->{$action}($id, $bodyData ?? []);
                                        } else {
                                            // Retrieve the resource and try to call the action on the object
                                            $resource = $stripe->{$resourceName}->retrieve($id);
                                            if (method_exists($resource, $action)) {
                                                $response = $resource->{$action}($bodyData ?? []);
                                            } else {
                                                return "Error: Action '$action' not found for resource '$resourceName'";
                                            }
                                        }
                                    } else {
                                        $response = $stripe->{$resourceName}->update($id, $bodyData ?? []);
                                    }
                                } else {
                                    $response = $stripe->{$resourceName}->update($id, $bodyData ?? []);
                                }
                            }
                            break;

                        case 'DELETE':
                            if (count($pathParts) < 2) {
                                return "Error: Resource ID required for DELETE request";
                            }

                            $id = $pathParts[1] ?? null;
                            if (!$id) {
                                return "Error: Resource ID required for DELETE request";
                            }

                            $response = $stripe->{$resourceName}->delete($id, $queryData);
                            break;

                        default:
                            return "Error: Unsupported HTTP method: $method";
                    }

                    // Convert response to JSON string
                    return json_encode($response->toArray());
                } catch (Exception $e) {
                    // Include more details in the error message if possible
                    $errorDetails = $e->getMessage();

                    if ($e instanceof ApiErrorException && method_exists($e, 'getJsonBody') && $e->getJsonBody()) {
                        $errorDetails = json_encode($e->getJsonBody());
                    }

                    return "Error making Stripe API call to '$method $path': " . $errorDetails;
                }
            });
    }

    public function getApiKey(): ?string
    {
        return $this->apiKey ?: config('services.stripe.secret');
    }
}
