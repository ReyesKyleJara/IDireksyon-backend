import assert from 'node:assert/strict';
import test from 'node:test';
import { MIN_IMAGE_SIZE, OUTPUT_SIZE, outputSide, outputType } from '../../resources/js/catalog-images.js';

test('PNG and WebP stay PNG so transparency can survive; JPEG stays JPEG', () => {
    assert.equal(outputType('image/png'), 'image/png');
    assert.equal(outputType('image/webp'), 'image/png');
    assert.equal(outputType('image/jpeg'), 'image/jpeg');
});

test('the crop targets 512 pixels without enlarging small crops or going below the server minimum', () => {
    assert.equal(OUTPUT_SIZE, 512);
    assert.equal(outputSide(1800), 512);
    assert.equal(outputSide(420.4), 420);
    assert.equal(outputSide(120), MIN_IMAGE_SIZE);
});
