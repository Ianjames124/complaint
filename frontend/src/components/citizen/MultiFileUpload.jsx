import React, { useState, useRef } from 'react';
import { Upload, X, Image as ImageIcon, Video, Loader2 } from 'lucide-react';
import api from '../../api/axios';
import toast from 'react-hot-toast';

const MultiFileUpload = ({ onFilesUploaded, maxImages = 5, maxVideos = 1 }) => {
  const [uploadedFiles, setUploadedFiles] = useState([]);
  const [uploading, setUploading] = useState(false);
  const fileInputRef = useRef(null);

  const handleFileSelect = async (e) => {
    const files = Array.from(e.target.files);
    
    // Validate file count
    const images = files.filter(f => f.type.startsWith('image/'));
    const videos = files.filter(f => f.type.startsWith('video/'));
    
    if (images.length + uploadedFiles.filter(f => f.file_type === 'image').length > maxImages) {
      toast.error(`Maximum ${maxImages} images allowed`);
      return;
    }
    
    if (videos.length + uploadedFiles.filter(f => f.file_type === 'video').length > maxVideos) {
      toast.error(`Maximum ${maxVideos} video allowed`);
      return;
    }
    
    // Validate file sizes (10MB max)
    const maxSize = 10 * 1024 * 1024; // 10MB
    const oversizedFiles = files.filter(f => f.size > maxSize);
    if (oversizedFiles.length > 0) {
      toast.error(`Some files exceed 10MB limit: ${oversizedFiles.map(f => f.name).join(', ')}`);
      return;
    }
    
    // Validate file types
    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'video/mp4', 'video/mov', 'video/avi'];
    const invalidFiles = files.filter(f => !allowedTypes.includes(f.type));
    if (invalidFiles.length > 0) {
      toast.error(`Invalid file types: ${invalidFiles.map(f => f.name).join(', ')}`);
      return;
    }
    
    // Upload files
    setUploading(true);
    try {
      const formData = new FormData();
      files.forEach(file => {
        formData.append('files[]', file); // PHP will receive this as $_FILES['files']
      });
      
      const response = await api.post('/citizen/upload-files.php', formData, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      });
      
      if (response.data.success) {
        const newFiles = response.data.data.files || [];
        setUploadedFiles(prev => [...prev, ...newFiles]);
        onFilesUploaded([...uploadedFiles, ...newFiles]);
        toast.success(`${newFiles.length} file(s) uploaded successfully`);
      } else {
        throw new Error(response.data.message || 'Upload failed');
      }
    } catch (error) {
      console.error('Upload error:', error);
      toast.error(error.response?.data?.message || error.message || 'Failed to upload files');
    } finally {
      setUploading(false);
      // Reset file input
      if (fileInputRef.current) {
        fileInputRef.current.value = '';
      }
    }
  };

  const handleRemoveFile = (fileId) => {
    setUploadedFiles(prev => {
      const updated = prev.filter(f => f.file_id !== fileId);
      onFilesUploaded(updated);
      return updated;
    });
    toast.success('File removed');
  };

  const getFilePreview = (file) => {
    const baseUrl = 'http://localhost/complaint/backend/';
    const fullPath = baseUrl + file.file_path;
    
    if (file.file_type === 'image') {
      return (
        <img
          src={fullPath}
          alt={file.file_name}
          className="w-full h-32 object-cover rounded-lg"
          onError={(e) => {
            e.target.src = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="100" height="100"%3E%3Crect fill="%23ddd" width="100" height="100"/%3E%3Ctext fill="%23999" x="50%25" y="50%25" text-anchor="middle" dy=".3em" font-size="12"%3EImage%3C/text%3E%3C/svg%3E';
          }}
        />
      );
    } else if (file.file_type === 'video') {
      return (
        <div className="w-full h-32 bg-gray-200 dark:bg-gray-700 rounded-lg flex items-center justify-center">
          <Video className="w-8 h-8 text-gray-400" />
        </div>
      );
    }
    return null;
  };

  return (
    <div className="space-y-4">
      {/* Upload Button */}
      <div
        onClick={() => fileInputRef.current?.click()}
        className={`border-2 border-dashed rounded-lg p-6 text-center cursor-pointer transition-colors duration-200 ${
          uploading
            ? 'border-primary-300 bg-primary-50 dark:bg-primary-900/20'
            : 'border-gray-300 dark:border-gray-600 hover:border-primary-500 dark:hover:border-primary-400 hover:bg-gray-50 dark:hover:bg-gray-800'
        }`}
      >
        <input
          ref={fileInputRef}
          type="file"
          multiple
          accept="image/jpeg,image/jpg,image/png,image/gif,image/webp,video/mp4,video/mov,video/avi"
          onChange={handleFileSelect}
          className="hidden"
          disabled={uploading}
        />
        {uploading ? (
          <div className="flex flex-col items-center gap-2">
            <Loader2 className="w-8 h-8 text-primary-600 dark:text-primary-400 animate-spin" />
            <p className="text-sm text-gray-600 dark:text-gray-400">Uploading files...</p>
          </div>
        ) : (
          <div className="flex flex-col items-center gap-2">
            <Upload className="w-8 h-8 text-gray-400" />
            <p className="text-sm font-medium text-gray-700 dark:text-gray-300">
              Click to upload files
            </p>
            <p className="text-xs text-gray-500 dark:text-gray-400">
              Images (max {maxImages}) or Video (max {maxVideos}) - Max 10MB per file
            </p>
          </div>
        )}
      </div>

      {/* File Preview Gallery */}
      {uploadedFiles.length > 0 && (
        <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
          {uploadedFiles.map((file) => (
            <div key={file.file_id} className="relative group">
              <div className="relative rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
                {getFilePreview(file)}
                <button
                  onClick={() => handleRemoveFile(file.file_id)}
                  className="absolute top-2 right-2 p-1 bg-red-500 text-white rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-200"
                  aria-label="Remove file"
                >
                  <X className="w-4 h-4" />
                </button>
              </div>
              <p className="mt-1 text-xs text-gray-600 dark:text-gray-400 truncate">
                {file.file_name}
              </p>
            </div>
          ))}
        </div>
      )}

      {/* File Count Summary */}
      {uploadedFiles.length > 0 && (
        <div className="text-sm text-gray-600 dark:text-gray-400">
          <p>
            {uploadedFiles.filter(f => f.file_type === 'image').length} image(s),{' '}
            {uploadedFiles.filter(f => f.file_type === 'video').length} video(s) uploaded
          </p>
        </div>
      )}
    </div>
  );
};

export default MultiFileUpload;

