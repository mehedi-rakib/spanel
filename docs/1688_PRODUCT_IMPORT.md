# 1688.com Product Import Feature

This feature allows you to import products from 1688.com using a third-party API service.

## Overview

The product import from 1688.com API feature includes:
- Single product import from 1688.com URL or product ID
- Bulk product import (multiple products at once)
- Automatic product data transformation
- Optional category and brand assignment
- Image processing (with TODO for local storage)

## Important Note

**1688.com does not provide a public API.** You will need to use a third-party API service provider to fetch product data from 1688.com.

## Setup Instructions

### 1. Choose an API Provider

Select one of the following options:

- **Paid API Services**: Search for "1688 API" or "Taobao API" services
- **Third-party Aggregators**: Services that provide unified access to Chinese marketplaces
- **Custom Web Scraping**: Build your own solution with proper rate limiting (advanced)

### 2. Configure Environment Variables

Add the following to your `.env` file:

```env
1688_API_ENDPOINT=your_api_endpoint_here
1688_API_KEY=your_api_key_here
```

Example:
```env
1688_API_ENDPOINT=https://api.yourprovider.com/v1/1688/product
1688_API_KEY=sk_live_abc123xyz456
```

### 3. Update the Service Class

The `Product1688ImportService` class is located at:
```
app/Services/Product1688ImportService.php
```

You need to update the following methods to match your API provider's response format:

#### a. Update `fetchProductFromApi()` method
Modify the HTTP request to match your API provider's requirements:

```php
$response = Http::timeout(30)
    ->withHeaders([
        'Authorization' => 'Bearer ' . $apiKey,
        'Accept' => 'application/json',
    ])
    ->get($apiEndpoint, [
        'product_id' => $productId,
    ]);
```

#### b. Update `transformApiData()` method
Map your API provider's response fields to the local product structure:

```php
private function transformApiData(array $apiData): array
{
    return [
        'name' => $apiData['your_api_title_field'],
        'unit_price' => $apiData['your_api_price_field'],
        'current_stock' => $apiData['your_api_stock_field'],
        // ... map other fields
    ];
}
```

### 4. Configure Image Handling (Optional)

By default, images are not downloaded locally. To enable automatic image downloads:

1. Uncomment the image download code in `processImages()` and `processThumbnail()` methods
2. Ensure your storage is properly configured in `config/filesystems.php`

## Usage

### Access the Import Page

Navigate to:
```
Admin Panel → Products → Import from 1688 API
```

Or directly:
```
/admin/products/api-import
```

### Single Product Import

1. Enter the 1688.com product URL or product ID
   - Example URL: `https://detail.1688.com/offer/123456789.html`
   - Example ID: `123456789`

2. (Optional) Select a default category
3. (Optional) Select a default brand
4. Click "Import Product"

### Bulk Product Import

1. Enter multiple product URLs or IDs (one per line or comma-separated)
2. (Optional) Select a default category for all products
3. (Optional) Select a default brand for all products
4. Click "Import Products"

### After Import

After importing products:
1. Review the imported products in the product list
2. Edit each product to:
   - Set proper categories
   - Upload/verify product images
   - Configure variations and attributes
   - Set pricing and stock levels
   - Add detailed descriptions

## API Response Format

Your API provider should return product data in a format similar to:

```json
{
  "productId": "123456789",
  "title": "Product Name",
  "price": 99.99,
  "stock": 100,
  "moq": 1,
  "unit": "piece",
  "description": "Product description",
  "mainImage": "https://example.com/image.jpg",
  "images": [
    "https://example.com/image1.jpg",
    "https://example.com/image2.jpg"
  ],
  "supplierId": "supplier123"
}
```

## Supported URL Formats

The system can extract product IDs from various 1688.com URL formats:

- `https://detail.1688.com/offer/123456789.html`
- `https://m.1688.com/offer/123456789.html`
- `https://detail.1688.com/offer/123456789.html?offerId=123456789`
- Or just the product ID: `123456789`

## Troubleshooting

### "Invalid product URL or ID"
- Verify the URL format is correct
- Try using just the product ID instead of the full URL

### "Failed to fetch product from API"
- Check your API credentials in `.env`
- Verify your API endpoint is correct
- Check if you have sufficient API credits/quota
- Review API provider's documentation for rate limits

### "Product imported but images missing"
- Images are not downloaded by default
- Implement the image download functionality in the service class
- Or manually upload images after import

### Products imported with status "Pending"
- This is normal - imported products require review
- Edit the product and set status to "Active" when ready

## File Structure

```
app/
├── Services/
│   └── Product1688ImportService.php          # Main import service
├── Http/
│   └── Controllers/
│       └── Admin/
│           └── Product/
│               └── ProductController.php       # Controller methods added
└── Enums/
    └── ViewPaths/
        └── Admin/
            └── Product.php                     # Route constants

resources/
└── views/
    └── admin-views/
        └── product/
            └── api-import.blade.php            # Import UI

routes/
└── admin/
    └── routes.php                              # Routes added

config/
└── services.php                                # API configuration
```

## Security Considerations

1. **API Keys**: Never commit API keys to version control
2. **Rate Limiting**: Implement rate limiting to avoid API quota exhaustion
3. **Validation**: Always validate imported data before saving
4. **Review Process**: Imported products should be reviewed before going live

## Future Enhancements

Potential improvements:
- Automatic image download and storage
- Product variation mapping
- Bulk category/brand mapping rules
- Import history and logging
- Scheduled imports
- Price update synchronization

## Support

For issues related to:
- **API Integration**: Contact your API provider
- **Feature Bugs**: Check application logs in `storage/logs/`
- **General Questions**: Refer to Laravel documentation

## License

This feature is part of the main application and follows the same license.
