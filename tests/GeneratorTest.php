<?php

namespace Based\TypeScript\Tests;

use Based\TypeScript\TypeScriptGenerator;

class GeneratorTest extends TestCase
{
    /** @test */
    public function it_works()
    {
        $output = @tempnam('/tmp', 'models.d.ts');

        $generator = new TypeScriptGenerator(
            generators: config('typescript.generators'),
            output: $output,
            autoloadDev: true
        );

        $generator->execute();

        $this->assertFileExists($output);

        $result = file_get_contents($output);

        $this->assertEquals(5, substr_count($result, 'interface'));
        $this->assertTrue(str_contains($result, 'sub_category?: Based.TypeScript.Tests.Models.Category | null;'));
        $this->assertTrue(str_contains($result, 'products_count?: number | null;'));
        $this->assertEquals(<<<TS
/**
 * This file is auto generated using 'php artisan typescript:generate'
 *
 * Changes to this file will be lost when the command is run again
 */

declare namespace Based.TypeScript.Tests.Models {
    export interface Feature {
        id: number;
        product_id: number;
        body: string;
        created_at: string | null;
        updated_at: string | null;
        product?: Based.TypeScript.Tests.Models.Product | null;
    }

    export interface Category {
        id: number;
        name: string;
        data: string | null;
        position: number;
        created_at: string | null;
        updated_at: string | null;
        products?: Array<Based.TypeScript.Tests.Models.Product> | null;
        products_count?: number | null;
    }

    export interface Product {
        id: number;
        category_id: number;
        sub_category_id: number;
        name: string;
        price: number;
        data: string | null;
        created_at: string | null;
        updated_at: string | null;
        category?: Based.TypeScript.Tests.Models.Category | null;
        sub_category?: Based.TypeScript.Tests.Models.Category | null;
        features?: Array<Based.TypeScript.Tests.Models.Feature> | null;
        features_count?: number | null;
        readonly mixed_accessor?: any;
        readonly typed_accessor?: string | null;
        readonly union_typed_accessor?: string | boolean | null;
        readonly class_typed_accessor?: any;
    }

    export interface User {
        id: number;
        name: string;
        email: string;
        is_admin: boolean;
        created_at: string | null;
        updated_at: string | null;
        roles?: Array<Based.TypeScript.Tests.Models.Role> | null;
        notifications?: Array<any> | null;
        read_notifications?: Array<any> | null;
        unread_notifications?: Array<any> | null;
        roles_count?: number | null;
        notifications_count?: number | null;
        read_notifications_count?: number | null;
        unread_notifications_count?: number | null;
        readonly first_name?: Based.TypeScript.Tests.Models.User | null;
    }

    export interface Role {
        id: number;
        name: string;
        created_at: string | null;
        updated_at: string | null;
        users?: Array<Based.TypeScript.Tests.Models.User> | null;
        users_count?: number | null;
    }

}

TS, $result);

        unlink($output);
    }
}
