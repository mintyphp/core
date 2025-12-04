<?php

namespace MintyPHP\Core\Debugger;

class View
{

    /**
     * Get the main view for the debugger
     * @param array<int,Request> $requests The history of requests
     * @return string The HTML code for the main view
     */
    public function getMainView(array $requests): string
    {
        $html = [];
        $html[] = '<div class="row">';
        $html[] = '<div class="col-md-4">';
        $html[] = $this->getRequestList($requests);
        $html[] = '</div>';
        $html[] = '<div class="col-md-8">';
        $html[] = '<div class="tab-content">';
        foreach ($requests as $i => $request):
            $html[] = '<div class="tab-pane ' . ($i == 0 ? 'active' : '') . '" id="debug-request-' . $i . '">';
            $html[] = $this->getTabList($i);
            $html[] = '<div class="tab-content">';
            $html[] = $this->getRoutingTabPane($i, $request);
            $html[] = $this->getExecutionTabPane($i, $request);
            $html[] = $this->getSessionTabPane($i, $request);
            $html[] = $this->getQueriesTabPane($i, $request);
            $html[] = $this->getApiCallsTabPane($i, $request);
            $html[] = $this->getCacheTabPane($i, $request);
            $html[] = $this->getLoggingTabPane($i, $request);
            $html[] = '</div>';
            $html[] = '</div>';
        endforeach;
        $html[] = '</div>';
        $html[] = '</div>';
        $html[] = '</div>';
        return implode("\n", $html);
    }

    /**
     * Get the caption for a request
     * @param Request $request The request object
     * @return string The caption for the request
     */
    public function getRequestCaption($request): string
    {
        $parts = array();
        if (isset($request->type)) {
            $parts[] = '<span class="badge pull-right">' . $request->status . '</span>';
        }
        $url = $request->route->request;
        if (strlen($url) > 40) {
            $shortUrl = substr($url, 0, 40) . '...';
        } else {
            $shortUrl = $url;
        }
        $parts[] = '<small>' . htmlentities($request->route->method .  ' ' . $shortUrl) . '</small>';
        return implode(' ', $parts);
    }

    /**
     * Get the list of requests
     * 
     * @param array<int,Request> $requests The history of requests
     * @return string The HTML code for the request list
     */
    public function getRequestList(array $requests): string
    {
        $html = array();
        $html[] = '<ul class="nav nav-pills nav-stacked">';
        /** @var Request $request */
        foreach ($requests as $i => $request) {
            if (!isset($request->status)) {
                continue;
            }
            $active = ($i == 0 ? 'active' : '');
            $html[] = '<li class="' . $active . '"><a href="#debug-request-' . $i . '" data-toggle="tab">';
            $html[] = $this->getRequestCaption($request);
            $html[] = '</a></li>';
        }
        $html[] = '</ul>';
        return implode("\n", $html);
    }

    /**
     * Get the tab list for a request
     * @param int $i The request index
     * @return string The HTML code for the tab list
     */
    public function getTabList($i): string
    {
        $html = array();
        $html[] = '<ul class="nav nav-pills">';
        $html[] = '<li class="active"><a class="debug-request-routing" href="#debug-request-' . $i . '-routing" data-toggle="tab">Routing</a></li>';
        $html[] = '<li><a class="debug-request-execution" href="#debug-request-' . $i . '-execution" data-toggle="tab">Execution</a></li>';
        $html[] = '<li><a class="debug-request-session" href="#debug-request-' . $i . '-session" data-toggle="tab">Session</a></li>';
        $html[] = '<li><a class="debug-request-queries" href="#debug-request-' . $i . '-queries" data-toggle="tab">Queries</a></li>';
        $html[] = '<li><a class="debug-request-api_calls" href="#debug-request-' . $i . '-api_calls" data-toggle="tab">API calls</a></li>';
        $html[] = '<li><a class="debug-request-cache" href="#debug-request-' . $i . '-cache" data-toggle="tab">Cache</a></li>';
        $html[] = '<li><a class="debug-request-logging" href="#debug-request-' . $i . '-logging" data-toggle="tab">Logging</a></li>';
        $html[] = '</ul>';
        return implode("\n", $html);
    }

    /**
     * Flatten a multi-dimensional array into a single-dimensional array with bracketed keys
     * @param array<string,mixed> $array The array to flatten
     * @param string $prefix The prefix for the keys
     * @return array<string,mixed> The flattened array
     */
    public function flattenParameters($array, $prefix = ''): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            if ($prefix) {
                $key = '[' . $key . ']';
            }
            if (is_array($value)) {
                $result = $result + $this->flattenParameters($value, $prefix . $key);
            } else {
                $result[$prefix . $key] = $value;
            }
        }
        return $result;
    }

    /**
     * Get the routing tab pane for a request
     * @param int $requestId The request index
     * @param Request $request The request object
     * @return string The HTML code for the routing tab pane
     */
    public function getRoutingTabPane($requestId, $request)
    {
        $html = array();
        $html[] = '<div class="tab-pane active" id="debug-request-' . $requestId . '-routing">';
        if ($request->route->method == 'GET' && count($request->route->getParameters)) {
            $html[] = '<div class="alert alert-info"><strong>Info:</strong> It is better to use path parameters than GET parameters</div>';
        }
        if ($request->route->method == 'POST' && !$request->route->csrfOk) {
            $html[] = '<div class="alert alert-danger"><strong>Error:</strong> CSRF token validation failed</div>';
        }
        $html[] = '<h4>Request</h4>';
        $html[] = '<div class="well well-sm">' . htmlentities($request->route->method . ' ' . $request->route->request) . '</div>';
        $html[] = '<h4>Files</h4>';
        $html[] = '<table class="table"><tbody>';
        $html[] = '<tr><th>Action</th><td>' . ($request->route->actionFile ?: '<em>None</em>') . '</td></tr>';
        $html[] = '<tr><th>View</th><td>' . $request->route->viewFile . '</td></tr>';
        $html[] = '<tr><th>Template</th><td>' . ($request->route->templateFile ?: '<em>None</em>') . '</td></tr>';
        $html[] = '</tbody></table>';
        $html[] = '<h4>Parameters</h4>';
        $html[] = '<table class="table"><tbody>';
        if (!count($request->route->urlParameters)) {
            $html[] = '<tr><td colspan="2"><em>None</em></td></tr>';
        } else {
            foreach ($request->route->urlParameters as $k => $v) {
                if (is_string($v)) {
                    $html[] = '<tr><th>' . htmlspecialchars($k) . '</th><td>' . htmlspecialchars($v) . '</td></tr>';
                }
            }
        }

        $html[] = '</tbody></table>';
        if (count($request->route->getParameters)) {
            $html[] = '<h4>$_GET</h4>';
            $html[] = '<table class="table"><tbody>';
            $request->route->getParameters = $this->flattenParameters($request->route->getParameters);
            foreach ($request->route->getParameters as $k => $v) {
                if (is_string($v)) {
                    $html[] = '<tr><th>' . htmlspecialchars($k) . '</th><td>' . htmlspecialchars($v) . '</td></tr>';
                }
            }
            $html[] = '</tbody></table>';
        }
        if (count($request->route->postParameters)) {
            $html[] = '<h4>$_POST</h4>';
            $html[] = '<table class="table"><tbody>';
            $request->route->postParameters = $this->flattenParameters($request->route->postParameters);
            foreach ($request->route->postParameters as $k => $v) {
                if (is_string($v)) {
                    $html[] = '<tr><th>' . htmlspecialchars($k) . '</th><td>' . htmlspecialchars($v) . '</td></tr>';
                }
            }
            $html[] = '</tbody></table>';
        }
        $html[] = '</div>';
        return implode("\n", $html);
    }

    /**
     * Get the execution tab pane for a request
     * @param int $requestId The request index
     * @param Request $request The request object
     * @return string The HTML code for the execution tab pane
     */
    public function getExecutionTabPane($requestId, $request): string
    {
        $html = array();
        $html[] = '<div class="tab-pane" id="debug-request-' . $requestId . '-execution">';
        $html[] = '<div class="row"><div class="col-md-10"><h4>Result</h4>';
        $html[] = '<div class="well well-sm">';
        if (!isset($request->type)) {
            $html[] = '???';
        } elseif ($request->type == 'abort') {
            $html[] = htmlspecialchars('Aborted: Exception, "die()" or "exit" encountered');
        } elseif ($request->type == 'ok') {
            $html[] = htmlspecialchars('Rendered page: ' . $request->route->url);
        } elseif ($request->type == 'json') {
            $html[] = htmlspecialchars('Rendered JSON: ' . $request->route->url);
        } elseif ($request->type == 'download') {
            $html[] = htmlspecialchars('Rendered download: ' . $request->route->url);
        } elseif ($request->type == 'redirect') {
            $html[] = htmlspecialchars('Redirected to: ' . $request->redirect);
        }
        $html[] = '</div>';
        $html[] = '</div>';
        $html[] = '<div class="col-md-2"><h4>Code</h4>';
        $html[] = '<div class="well well-sm">';
        $html[] = htmlspecialchars(strval($request->status));
        $html[] = '</div>';
        $html[] = '</div>';
        $html[] = '</div>';
        $time = (int) $request->start;
        $time = date('H:i:s', $time) . sprintf('%.3f', $request->start - $time);
        $duration = isset($request->duration) ? sprintf('%.2f ms', $request->duration * 1000) : '???';
        $memory = isset($request->memory) ? sprintf('%.2f MB', $request->memory / 1000000) : '???';
        $html[] = '<table class="table"><thead>';
        $html[] = '<tr><th>Time</th><th>Duration</th><th>Peak memory</th><th>Run as</th></tr>';
        $html[] = '</thead><tbody><tr>';
        $html[] = '<td>' . $time . '</td><td>' . $duration . '</td><td>' . $memory . '</td><td>' . $request->user . '</td>';
        $html[] = '</tr></tbody></table>';
        $html[] = '<h4>Classes</h4>';
        $html[] = '<table class="table"><tbody>';
        $total = 0;
        $count = 0;
        foreach ($request->classes as $filename) {
            $count++;
            $path = str_replace(realpath(getcwd() ?: '.') . '/', '', $filename);
            $path = htmlspecialchars($path);
            $size = filesize($filename);
            $total += $size;
            $size = sprintf('%.2f kB', $size / 1000);
            $html[] = '<tr><td>' . $count . '.</td><td>' . $path . '</td><td>' . $size . '</td></tr>';
        }
        $total = sprintf('%.2f kB', $total / 1000);
        $html[] = '<tr><td colspan="2"><strong>Total</strong></td><td><strong>' . $total . '</strong></td></tr>';
        $html[] = '</tbody></table>';
        $html[] = '</div>';
        return implode("\n", $html);
    }

    /**
     * Get the session tab pane for a request
     * @param int $requestId The request index
     * @param Request $request The request object
     * @return string The HTML code for the session tab pane
     */
    public function getSessionTabPane($requestId, $request): string
    {
        $html = array();
        $html[] = '<div class="tab-pane" id="debug-request-' . $requestId . '-session">';
        $html[] = '<h4>Before</h4>';
        $html[] = '<pre>';
        $html[] = $request->sessionBefore;
        $html[] = '</pre>';
        $html[] = '<h4>After</h4>';
        $html[] = '<pre>';
        $html[] = $request->sessionAfter;
        $html[] = '</pre>';
        $html[] = '</div>';
        return implode("\n", $html);
    }

    /**
     * Get the tab list for a query
     * @param int $requestId The request index
     * @param int $i The query index
     * @param int $args The number of arguments
     * @param int $rows The number of result rows
     * @return string The HTML code for the tab list
     */
    public function getQueriesTabPaneTabList($requestId, $i, $args, $rows): string
    {
        $html = array();
        $html[] = '<ul class="nav nav-pills">';
        $args = $args ? '<span class="badge pull-right">' . $args . '</span>' : '';
        $html[] = '<li class="active"><a href="#debug-request-' . $requestId . '-query-' . $i . '-arguments" data-toggle="tab">Arguments' . $args . '</a></li>';
        $html[] = '<li><a href="#debug-request-' . $requestId . '-query-' . $i . '-explain" data-toggle="tab">Explain</a></li>';
        $rows = $rows ? '<span class="badge pull-right">' . $rows . '</span>' : '';
        $html[] = '<li><a href="#debug-request-' . $requestId . '-query-' . $i . '-result" data-toggle="tab">Result' . $rows . '</a></li>';
        $html[] = '</ul>';
        return implode("\n", $html);
    }

    /**
     * Get the tab pane for a query
     * @param int $requestId The request index
     * @param Query $query The query object
     * @param int $queryId The query index
     * @return string The HTML code for the tab pane
     */
    public function getQueriesTabPaneTabPaneArguments($requestId, $query, $queryId): string
    {
        $html = [];
        $html[] = '<div class="tab-pane active" id="debug-request-' . $requestId . '-query-' . $queryId . '-arguments">';
        $html[] = '<pre style="margin-top:10px;">';
        $html[] = 'PREPARE `query` FROM \'' . $query->equery . '\';';
        $params = '';
        foreach ($query->arguments as $i => $argument) {
            if (!$params) {
                $html[] = '';
                $params = ' USING ';
            }
            $params .= '@argument' . (is_int($i) ? $i + 1 : $i) . ',';
            $html[] = 'SET @argument' . (is_int($i) ? $i + 1 : $i) . ' = ' . htmlspecialchars(var_export($argument, true)) . ';';
        }
        $params = rtrim($params, ',');
        $html[] = '';
        $html[] = 'EXECUTE `query`' . $params . ';';
        $html[] = 'DEALLOCATE PREPARE `query`;';
        $html[] = '</pre></div>';
        return implode("\n", $html);
    }

    /**
     * Get the tab pane for a query
     * @param int $requestId The request index
     * @param Query $query The query object
     * @param int $queryId The query index
     * @return string The HTML code for the tab pane
     */
    public function getQueriesTabPaneTabPaneResult($requestId, $query, $queryId): string
    {
        $html = [];
        $html[] = '<div class="tab-pane" id="debug-request-' . $requestId . '-query-' . $queryId . '-result">';
        $html[] = '<table class="table"><thead>';
        if (is_int($query->result)) {
            $html[] = '<tr><th>Field</th><th>Value</th></tr>';
            $html[] = '</thead><tbody>';
            $html[] = '<tr><td>Affected rows</td><td>' . $query->result . '</td></tr>';
        } else if (is_array($query->result)) {
            $html[] = '<tr><th>#</th><th>Table</th><th>Field</th><th>Value</th></tr>';
            $html[] = '</thead><tbody>';
            foreach ($query->result as $i => $tables) {
                $f = 0;
                $fc = array_sum(array_map("count", $tables));
                foreach ($tables as $table => $fields) {
                    $t = 0;
                    $tc = count($fields);
                    foreach ($fields as $field => $value) {
                        $rowCell = $f ? '' : '<td rowspan="' . $fc . '">' . ($i + 1) . '</td>';
                        $tableCell = $t ? '' : '<td rowspan="' . $tc . '">' . $table . '</td>';
                        $html[] = '<tr>' . $rowCell . $tableCell . '<td>' . $field . '</td><td>' . htmlspecialchars(var_export($value, true)) . '</td></tr>';
                        $t++;
                        $f++;
                    }
                }
            }
        } else {
            $html[] = '</thead><tbody>';
        }
        $html[] = '</tbody>';
        $html[] = '</table></div>';
        return implode("\n", $html);
    }

    /**
     * Get the tab pane for a query
     * @param int $requestId The request index
     * @param Query $query The query object
     * @param int $queryId The query index
     * @return string The HTML code for the tab pane
     */
    public function getQueriesTabPaneTabPaneExplain($requestId, $query, $queryId): string
    {
        $html = [];
        $html[] = '<div class="tab-pane" id="debug-request-' . $requestId . '-query-' . $queryId . '-explain">';
        $html[] = '<table class="table"><thead>';
        if (is_int($query->explain)) {
            $html[] = '<tr><th>Field</th><th>Value</th></tr>';
            $html[] = '</thead><tbody>';
            $html[] = '<tr><td>Affected rows</td><td>' . $query->explain . '</td></tr>';
        } else if (is_array($query->explain)) {
            $html[] = '<tr><th>#</th><th>Table</th><th>Field</th><th>Value</th></tr>';
            $html[] = '</thead><tbody>';
            foreach ($query->explain as $i => $tables) {
                $f = 0;
                $fc = array_sum(array_map("count", $tables));
                foreach ($tables as $table => $fields) {
                    $t = 0;
                    $tc = count($fields);
                    foreach ($fields as $field => $value) {
                        $rowCell = $f ? '' : '<td rowspan="' . $fc . '">' . ($i + 1) . '</td>';
                        $tableCell = $t ? '' : '<td rowspan="' . $tc . '">' . $table . '</td>';
                        $html[] = '<tr>' . $rowCell . $tableCell . '<td>' . $field . '</td><td>' . htmlspecialchars(var_export($value, true)) . '</td></tr>';
                        $t++;
                        $f++;
                    }
                }
            }
        } else {
            $html[] = '</thead><tbody>';
        }
        $html[] = '</tbody>';
        $html[] = '</table></div>';
        return implode("\n", $html);
    }

    /**
     * Get the queries tab pane for a request
     * @param int $requestId The request index
     * @param Request $request The request object
     * @return string The HTML code for the queries tab pane
     */
    public function getQueriesTabPane($requestId, $request): string
    {
        $html = array();
        $html[] = '<div class="tab-pane" id="debug-request-' . $requestId . '-queries">';
        $html[] = '<table class="table"><thead>';
        $html[] = '<tr><th>DB</th><th>Duration</th></tr>';
        $html[] = '</thead><tbody>';
        $count = 0;
        $total = 0;
        foreach ($request->queries as $i => $query) {
            $count++;
            $total += $query->duration;
            $html[] = '<tr>';
            $html[] = '<td><a href="#" onclick="$(\'#debug-request-' . $requestId . '-query-' . $i . '\').toggle(); return false;">' . $query->query . '</a></td>';
            $html[] = '<td>' . sprintf('%.2f ms', $query->duration * 1000) . '</td>';
            $html[] = '</tr>';
            $html[] = '<tr style="display:none;" id="debug-request-' . $requestId . '-query-' . $i . '"><td colspan="5">';

            $html[] = $this->getQueriesTabPaneTabList($requestId, $i, count($query->arguments), is_array($query->result) ? count($query->result) : 1);
            $html[] = '<div class="tab-content">';
            $html[] = $this->getQueriesTabPaneTabPaneArguments($requestId, $query, $i);
            $html[] = $this->getQueriesTabPaneTabPaneResult($requestId, $query, $i);
            $html[] = $this->getQueriesTabPaneTabPaneExplain($requestId, $query, $i);
            $html[] = '</div>';

            $html[] = '</td></tr>';
        }
        $html[] = '<tr><td><strong>' . $count . ' queries</strong></td>';
        $html[] = '<td>' . sprintf('%.2f ms', $total * 1000) . '</td></tr>';
        $html[] = '</tbody></table>';
        $html[] = '</div>';
        return implode("\n", $html);
    }

    /**
     * Get the API calls tab pane for a request
     * @param int $requestId The request index
     * @param Request $request The request object
     * @return string The HTML code for the API calls tab pane
     */
    public function getApiCallsTabPane($requestId, $request): string
    {
        $html = array();
        $html[] = '<div class="tab-pane" id="debug-request-' . $requestId . '-api_calls">';
        $html[] = '<table class="table"><thead>';
        $html[] = '<tr><th>URL</th><th class="">Duration</th></tr>';
        $html[] = '</thead><tbody>';
        $count = 0;
        $total = 0;
        foreach ($request->apiCalls as $i => $call) {
            $count++;
            $total += $call->duration;
            $url = $call->url;
            if (strlen($url) > 40) {
                $shortUrl = substr($url, 0, 40) . '...';
            } else {
                $shortUrl = $url;
            }
            $html[] = '<tr>';
            $html[] = '<td><a href="#" onclick="$(\'#debug-request-' . $requestId . '-api_call-' . $i . '\').toggle(); return false;">' . $call->method . ' ' . $shortUrl . '<span class="badge pull-right">' . $call->status . '</span></a></td>';
            $html[] = '<td>' . sprintf('%.2f ms', $call->duration * 1000) . '</td>';
            $html[] = '</tr>';
            $html[] = '<tr style="display:none;" id="debug-request-' . $requestId . '-api_call-' . $i . '"><td colspan="5">';

            $html[] = '<table class="table"><thead>';
            $html[] = '<tr><th>Category</th><th>Key</th><th>Value</th></tr>';
            $html[] = '</thead><tbody>';
            $tables = array();
            $tables['details'] = array();
            $tables['details']['method'] = $call->method;
            $tables['details']['url'] = '<a href="' . $url . '">Visit</a>';
            $tables['details']['status'] = $call->status;
            $tables['details']['data_sent'] = $call->data ? '<a href="data:application/json;charset=UTF-8;base64,' . base64_encode($call->data) . '">' . strlen($call->data) . ' bytes</a>' : '-';
            $tables['details']['data_received'] = $call->body ? '<a href="data:application/json;charset=UTF-8;base64,' . base64_encode($call->body) . '">View (' . strlen($call->body) . ' bytes)</a>' : '-';
            $tables['details']['headers_sent'] = $call->headers ? '<a href="data:application/json;charset=UTF-8;base64,' . base64_encode(json_encode($call->headers, JSON_PRETTY_PRINT + JSON_UNESCAPED_SLASHES + JSON_UNESCAPED_UNICODE) ?: '') . '">' . count($call->headers) . ' headers</a>' : '-';
            $tables['details']['headers_received'] = $call->responseHeaders ? '<a href="data:application/json;charset=UTF-8;base64,' . base64_encode(json_encode($call->responseHeaders, JSON_PRETTY_PRINT + JSON_UNESCAPED_SLASHES + JSON_UNESCAPED_UNICODE) ?: '') . '">' . count($call->responseHeaders) . ' headers</a>' : '-';
            $tables['timing'] = array_map(function ($v) {
                return sprintf('%.2f ms', $v * 1000);
            }, $call->timing);

            $tables['options'] = $call->options;
            foreach ($tables as $table => $fields) {
                $t = 0;
                $tc = count($fields);
                foreach ($fields as $field => $value) {
                    $tableCell = $t ? '' : '<td rowspan="' . $tc . '">' . $table . '</td>';
                    $html[] = '<tr>' . $tableCell . '<td>' . $field . '</td><td>' . $value . '</td></tr>';
                    $t++;
                }
            }
            $html[] = '</tbody>';
            $html[] = '</table>';

            $html[] = '</td></tr>';
        }
        $html[] = '<tr><td><strong>' . $count . ' API calls</strong></td>';
        $html[] = '<td>' . sprintf('%.2f ms', $total * 1000) . '</td></tr>';
        $html[] = '</tbody></table>';
        $html[] = '</div>';
        return implode("\n", $html);
    }

    /**
     * Get the cache tab pane for a request
     * @param int $requestId The request index
     * @param Request $request The request object
     * @return string The HTML code for the cache tab pane
     */
    public function getCacheTabPane($requestId, $request)
    {
        $html = array();
        $html[] = '<div class="tab-pane" id="debug-request-' . $requestId . '-cache">';
        $html[] = '<table class="table"><thead>';
        $html[] = '<tr><th>Command</th><th>Result</th><th class="">Duration</th></tr>';
        $html[] = '</thead><tbody>';
        $count = 0;
        $total = 0;
        foreach ($request->cache as $i => $call) {
            $count++;
            $total += $call->duration;

            $html[] = '<tr>';
            $html[] = '<td>' . strtoupper($call->command) . ' ' . implode(' ', $call->arguments) . '</td>';
            $html[] = '<td>' . $call->result . '</td>';
            $html[] = '<td>' . sprintf('%.2f ms', $call->duration * 1000) . '</td>';
            $html[] = '</tr>';
        }
        $html[] = '<tr><td colspan="2"><strong>' . $count . ' commands</strong></td>';
        $html[] = '<td>' . sprintf('%.2f ms', $total * 1000) . '</td></tr>';
        $html[] = '</tbody></table>';
        $html[] = '</div>';
        return implode("\n", $html);
    }

    /**
     * Get the logging tab pane for a request
     * @param int $requestId The request index
     * @param Request $request The request object
     * @return string The HTML code for the logging tab pane
     */
    public function getLoggingTabPane($requestId, $request)
    {
        $html = array();
        $html[] = '<div class="tab-pane" id="debug-request-' . $requestId . '-logging">';
        $html[] = '<br/><pre>';
        foreach ($request->log as $log) {
            $html[] = htmlspecialchars($log) . "\n";
        }
        $html[] = '</pre>';
        $html[] = '</div>';
        return implode("\n", $html);
    }
}
