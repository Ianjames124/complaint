import React, { useState } from 'react';
import { X, ZoomIn } from 'lucide-react';
import api from '../../api/axios';

const ImageGallery = ({ images, title = 'Evidence Images' }) => {
  const [selectedImage, setSelectedImage] = useState(null);

  if (!images || images.length === 0) {
    return null;
  }

  // Get full image URL
  const getImageUrl = (image) => {
    const baseURL = api.defaults.baseURL || 'http://localhost/complaint/backend/api';
    
    if (typeof image === 'string') {
      // If it's just a URL string
      return image.startsWith('http') ? image : `${baseURL}${image}`;
    }
    // If it's an object with url or path
    if (image.url) {
      if (image.url.startsWith('http')) {
        return image.url;
      }
      if (image.url.startsWith('/')) {
        return `${baseURL}${image.url}`;
      }
      return `${baseURL}/${image.url}`;
    }
    if (image.path) {
      return `${baseURL}/complaints/image.php?file=${encodeURIComponent(image.path)}`;
    }
    if (image.image_path) {
      return `${baseURL}/complaints/image.php?file=${encodeURIComponent(image.image_path)}`;
    }
    return null;
  };

  return (
    <>
      <div className="mb-4">
        <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-3">
          {title} ({images.length})
        </h3>
        <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
          {images.map((image, index) => {
            const imageUrl = getImageUrl(image);
            if (!imageUrl) return null;

            return (
              <div
                key={index}
                className="relative group cursor-pointer"
                onClick={() => setSelectedImage(imageUrl)}
              >
                <div className="relative aspect-square overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-gray-800">
                  <img
                    src={imageUrl}
                    alt={`Evidence ${index + 1}`}
                    className="w-full h-full object-cover transition-transform duration-200 group-hover:scale-105"
                    onError={(e) => {
                      e.target.src = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="100" height="100"%3E%3Crect fill="%23ddd" width="100" height="100"/%3E%3Ctext fill="%23999" x="50%25" y="50%25" text-anchor="middle" dy=".3em" font-size="12"%3EImage%3C/text%3E%3C/svg%3E';
                    }}
                  />
                  <div className="absolute inset-0 bg-black/0 group-hover:bg-black/20 transition-colors duration-200 flex items-center justify-center">
                    <ZoomIn className="w-6 h-6 text-white opacity-0 group-hover:opacity-100 transition-opacity" />
                  </div>
                </div>
                {image.file_name && (
                  <p className="mt-1 text-xs text-gray-600 dark:text-gray-400 truncate">
                    {image.file_name}
                  </p>
                )}
              </div>
            );
          })}
        </div>
      </div>

      {/* Full Screen Modal */}
      {selectedImage && (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center bg-black/90 p-4"
          onClick={() => setSelectedImage(null)}
        >
          <button
            onClick={() => setSelectedImage(null)}
            className="absolute top-4 right-4 text-white hover:text-gray-300 transition-colors"
            aria-label="Close"
          >
            <X className="w-8 h-8" />
          </button>
          <img
            src={selectedImage}
            alt="Full size"
            className="max-w-full max-h-full object-contain"
            onClick={(e) => e.stopPropagation()}
          />
        </div>
      )}
    </>
  );
};

export default ImageGallery;

