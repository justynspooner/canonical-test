<?php

declare(strict_types=1);

use DOVU\CanonicalJson\JsonCanonicalizer;

beforeEach(function () {
    $this->canonicalizer = new JsonCanonicalizer;
});

describe('JsonCanonicalizer', function () {
    describe('basic types', function () {
        it('canonicalizes null', function () {
            expect($this->canonicalizer->canonicalize(null))->toBe('null');
        });

        it('canonicalizes booleans', function () {
            expect($this->canonicalizer->canonicalize(true))->toBe('true');
            expect($this->canonicalizer->canonicalize(false))->toBe('false');
        });

        it('canonicalizes integers', function () {
            expect($this->canonicalizer->canonicalize(0))->toBe('0');
            expect($this->canonicalizer->canonicalize(42))->toBe('42');
            expect($this->canonicalizer->canonicalize(-100))->toBe('-100');
        });

        it('canonicalizes floats', function () {
            expect($this->canonicalizer->canonicalize(3.14))->toBe('3.14');
            expect($this->canonicalizer->canonicalize(-2.5))->toBe('-2.5');
        });

        it('canonicalizes strings', function () {
            expect($this->canonicalizer->canonicalize('hello'))->toBe('"hello"');
            expect($this->canonicalizer->canonicalize(''))->toBe('""');
        });

        it('escapes special characters in strings', function () {
            expect($this->canonicalizer->canonicalize("line1\nline2"))->toBe('"line1\nline2"');
            expect($this->canonicalizer->canonicalize("tab\there"))->toBe('"tab\there"');
            expect($this->canonicalizer->canonicalize('quote"here'))->toBe('"quote\"here"');
        });
    });

    describe('arrays', function () {
        it('canonicalizes empty arrays', function () {
            expect($this->canonicalizer->canonicalize([]))->toBe('[]');
        });

        it('canonicalizes simple arrays', function () {
            expect($this->canonicalizer->canonicalize([1, 2, 3]))->toBe('[1,2,3]');
        });

        it('canonicalizes mixed arrays', function () {
            expect($this->canonicalizer->canonicalize([1, 'two', true, null]))->toBe('[1,"two",true,null]');
        });

        it('canonicalizes nested arrays', function () {
            expect($this->canonicalizer->canonicalize([[1, 2], [3, 4]]))->toBe('[[1,2],[3,4]]');
        });
    });

    describe('objects', function () {
        it('canonicalizes empty objects', function () {
            expect($this->canonicalizer->canonicalize((object) []))->toBe('{}');
        });

        it('canonicalizes simple objects', function () {
            $result = $this->canonicalizer->canonicalize(['name' => 'John']);
            expect($result)->toBe('{"name":"John"}');
        });

        it('sorts object keys alphabetically', function () {
            $result = $this->canonicalizer->canonicalize(['z' => 1, 'a' => 2, 'm' => 3]);
            expect($result)->toBe('{"a":2,"m":3,"z":1}');
        });

        it('sorts keys using UTF-16BE comparison', function () {
            // RFC 8785 requires UTF-16BE code unit comparison
            // Test with Unicode characters that sort differently in UTF-8 vs UTF-16BE
            $result = $this->canonicalizer->canonicalize([
                "\u{20AC}" => 'euro',  // Euro sign
                "\u{000A}" => 'newline', // Newline
                'a' => 'letter',
            ]);
            // Newline (0x000A) < 'a' (0x0061) < Euro (0x20AC) in UTF-16BE
            expect($result)->toBe('{"\n":"newline","a":"letter","€":"euro"}');
        });

        it('canonicalizes nested objects', function () {
            $result = $this->canonicalizer->canonicalize([
                'outer' => ['inner' => 'value'],
            ]);
            expect($result)->toBe('{"outer":{"inner":"value"}}');
        });

        it('produces no whitespace', function () {
            $result = $this->canonicalizer->canonicalize([
                'key1' => 'value1',
                'key2' => [1, 2, 3],
            ]);
            expect($result)->not->toContain(' ');
            expect($result)->not->toContain("\n");
            expect($result)->not->toContain("\t");
        });
    });

    describe('complex structures', function () {
        it('handles deeply nested structures', function () {
            $input = [
                'level1' => [
                    'level2' => [
                        'level3' => [
                            'value' => 'deep',
                        ],
                    ],
                ],
            ];
            $result = $this->canonicalizer->canonicalize($input);
            expect($result)->toBe('{"level1":{"level2":{"level3":{"value":"deep"}}}}');
        });

        it('handles arrays containing objects', function () {
            $input = [
                'items' => [
                    ['z' => 1, 'a' => 2],
                    ['y' => 3, 'b' => 4],
                ],
            ];
            $result = $this->canonicalizer->canonicalize($input);
            // Array order preserved, but object keys sorted
            expect($result)->toBe('{"items":[{"a":2,"z":1},{"b":4,"y":3}]}');
        });
    });

    describe('canonicalizeJson (raw JSON string input)', function () {
        it('canonicalizes a JSON string with unsorted keys', function () {
            $json = '{"z":1,"a":2,"m":3}';
            $result = $this->canonicalizer->canonicalizeJson($json);
            expect($result)->toBe('{"a":2,"m":3,"z":1}');
        });

        it('canonicalizes a JSON string with whitespace', function () {
            $json = '{
                "name": "John",
                "age": 30,
                "active": true
            }';
            $result = $this->canonicalizer->canonicalizeJson($json);
            expect($result)->toBe('{"active":true,"age":30,"name":"John"}');
        });

        it('canonicalizes nested JSON objects', function () {
            $json = '{"outer":{"z":1,"a":2},"inner":{"y":3,"b":4}}';
            $result = $this->canonicalizer->canonicalizeJson($json);
            expect($result)->toBe('{"inner":{"b":4,"y":3},"outer":{"a":2,"z":1}}');
        });

        it('preserves JSON arrays order', function () {
            $json = '[3,1,2]';
            $result = $this->canonicalizer->canonicalizeJson($json);
            expect($result)->toBe('[3,1,2]');
        });

        it('handles JSON with numbers correctly', function () {
            $json = '{"float":3.14,"int":42,"scientific":1e30}';
            $result = $this->canonicalizer->canonicalizeJson($json);
            expect($result)->toBe('{"float":3.14,"int":42,"scientific":1e+30}');
        });

        it('throws exception for invalid JSON', function () {
            $this->canonicalizer->canonicalizeJson('not valid json');
        })->throws(JsonException::class);

        it('handles empty JSON object', function () {
            expect($this->canonicalizer->canonicalizeJson('{}'))->toBe('{}');
        });

        it('handles empty JSON array', function () {
            expect($this->canonicalizer->canonicalizeJson('[]'))->toBe('[]');
        });

        it('handles JSON with unicode characters', function () {
            $json = '{"currency":"€","greeting":"Hello, 世界"}';
            $result = $this->canonicalizer->canonicalizeJson($json);
            expect($result)->toBe('{"currency":"€","greeting":"Hello, 世界"}');
        });

        it('simulates Laravel raw request body canonicalization', function () {
            // This simulates what you'd get from $request->getContent()
            $rawBody = '{"webhook_id":"abc123","timestamp":1234567890,"data":{"user":"john","action":"login"},"aNullValue":null}';
            $result = $this->canonicalizer->canonicalizeJson($rawBody);
            // Keys should be sorted at all levels
            expect($result)->toBe('{"aNullValue":null,"data":{"action":"login","user":"john"},"timestamp":1234567890,"webhook_id":"abc123"}');
        });
    });
});
