import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import toast from 'react-hot-toast';
import api from '../../api/axios';
import CitizenNavbar from '../../components/navbar/CitizenNavbar';
import Card from '../../components/common/Card';
import Button from '../../components/common/Button';
import MultiFileUpload from '../../components/citizen/MultiFileUpload';
import PrioritySelector from '../../components/common/PrioritySelector';
import { FileText, Loader2 } from 'lucide-react';

const SubmitComplaint = () => {
  const [form, setForm] = useState({
    subject: '',
    description: '',
    category: 'General',
    location: '',
    priority_level: 'Medium',
  });
  const [uploadedFiles, setUploadedFiles] = useState([]);
  const [selectedImages, setSelectedImages] = useState([]);
  const [imagePreviews, setImagePreviews] = useState([]);
  const [loading, setLoading] = useState(false);
  const navigate = useNavigate();

  const handleChange = (e) => {
    const { name, value } = e.target;
    setForm((prev) => ({
      ...prev,
      [name]: value
    }));
  };

  const handleFilesUploaded = (files) => {
    setUploadedFiles(files);
  };

  const handleImageChange = (e) => {
    const files = Array.from(e.target.files);
    
    // Limit to 5 images
    if (files.length + selectedImages.length > 5) {
      toast.error('Maximum 5 images allowed');
      return;
    }
    
    // Validate file types and sizes
    const validFiles = [];
    const previews = [];
    
    files.forEach(file => {
      // Check file type
      if (!file.type.startsWith('image/')) {
        toast.error(`${file.name} is not an image file`);
        return;
      }
      
      // Check file size (5MB max)
      if (file.size > 5 * 1024 * 1024) {
        toast.error(`${file.name} exceeds 5MB size limit`);
        return;
      }
      
      validFiles.push(file);
      
      // Create preview
      const reader = new FileReader();
      reader.onload = (e) => {
        previews.push(e.target.result);
        if (previews.length === validFiles.length) {
          setImagePreviews(prev => [...prev, ...previews]);
        }
      };
      reader.readAsDataURL(file);
    });
    
    setSelectedImages(prev => [...prev, ...validFiles]);
  };

  const removeImage = (index) => {
    setSelectedImages(prev => prev.filter((_, i) => i !== index));
    setImagePreviews(prev => prev.filter((_, i) => i !== index));
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    
    // Client-side validation
    if (!form.subject || form.subject.trim().length < 1) {
      toast.error('Subject is required');
      return;
    }
    
    if (!form.description || form.description.trim().length < 10) {
      toast.error('Description must be at least 10 characters long');
      return;
    }
    
    if (!form.location || form.location.trim().length < 1) {
      toast.error('Location is required');
      return;
    }
    
    setLoading(true);
    try {
      // Prepare payload with file IDs (only include valid file_ids)
      const fileIds = uploadedFiles
        .map(f => f.file_id)
        .filter(id => id && typeof id === 'number');
      
      let response;
      
      // If images are selected, use FormData (multipart/form-data)
      if (selectedImages.length > 0) {
        const formData = new FormData();
        formData.append('title', form.subject.trim());
        formData.append('description', form.description.trim());
        formData.append('category', form.category || 'General');
        formData.append('location', form.location.trim());
        formData.append('priority_level', form.priority_level || 'Medium');
        
        // Append images
        selectedImages.forEach((image, index) => {
          formData.append('images[]', image);
        });
        
        // Include file_ids if any
        if (fileIds.length > 0) {
          formData.append('file_ids', JSON.stringify(fileIds));
        }

        console.log('Submitting complaint with FormData (images:', selectedImages.length, ')');

        response = await api.post('/citizen/create-complaint.php', formData, {
          headers: {
            'Content-Type': 'multipart/form-data',
          },
        });
      } else {
        // Use JSON if no images
        const payload = {
          title: form.subject.trim(),
          description: form.description.trim(),
          category: form.category || 'General',
          location: form.location.trim(),
          priority_level: form.priority_level || 'Medium',
        };
        
        if (fileIds.length > 0) {
          payload.file_ids = fileIds;
        }

        console.log('Submitting complaint with JSON payload');

        response = await api.post('/citizen/create-complaint.php', payload, {
          headers: {
            'Content-Type': 'application/json',
          },
        });
      }
      
      if (response.data && response.data.success) {
        toast.success(response.data.message || 'Complaint submitted successfully');
        setForm({
          subject: '',
          description: '',
          category: 'General',
          location: '',
          priority_level: 'Medium',
        });
        setUploadedFiles([]);
        setSelectedImages([]);
        setImagePreviews([]);
        // Navigate after a short delay to show success message
        setTimeout(() => {
          navigate('/citizen/my-complaints');
        }, 1000);
      } else {
        throw new Error(response.data?.message || 'Failed to submit complaint');
      }
    } catch (error) {
      console.error('Submit complaint error:', error);
      console.error('Error response:', error.response);
      
      // Extract detailed error message
      let errorMessage = 'Unable to submit complaint. Please try again.';
      
      if (error.response) {
        // Server responded with error
        const data = error.response.data;
        if (data && data.message) {
          errorMessage = data.message;
        } else if (error.response.status === 500) {
          errorMessage = 'Server error occurred. Please contact support if this persists.';
        } else if (error.response.status === 400) {
          errorMessage = data?.message || 'Invalid data provided. Please check your input.';
        } else if (error.response.status === 401) {
          errorMessage = 'Your session has expired. Please login again.';
          setTimeout(() => navigate('/login'), 2000);
        } else if (error.response.status === 403) {
          errorMessage = 'You do not have permission to submit complaints.';
        }
      } else if (error.request) {
        // Request was made but no response received
        errorMessage = 'Network error. Please check your connection and try again.';
      } else {
        // Error setting up the request
        errorMessage = error.message || 'An unexpected error occurred.';
      }
      
      toast.error(errorMessage, {
        duration: 5000,
      });
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-gray-50 dark:bg-gray-900">
      <CitizenNavbar />
      <main className="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div className="mb-8">
          <div className="flex items-center gap-3 mb-2">
            <FileText className="w-8 h-8 text-primary-600 dark:text-primary-400" />
            <h1 className="text-3xl font-bold text-gray-900 dark:text-gray-100">
              Submit Complaint
            </h1>
          </div>
          <p className="text-gray-600 dark:text-gray-400">
            Report an issue or concern to the administration
          </p>
        </div>

        <Card>
          <form onSubmit={handleSubmit} className="space-y-6">
            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Subject <span className="text-red-500">*</span>
              </label>
              <input
                type="text"
                name="subject"
                value={form.subject}
                onChange={handleChange}
                required
                className="input-field"
                placeholder="Brief description of your complaint"
                disabled={loading}
              />
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Category <span className="text-red-500">*</span>
                </label>
                <select
                  name="category"
                  value={form.category}
                  onChange={handleChange}
                  className="input-field"
                  disabled={loading}
                >
                  <option value="General">General</option>
                  <option value="Infrastructure">Infrastructure</option>
                  <option value="Sanitation">Sanitation</option>
                  <option value="Security">Security</option>
                  <option value="Other">Other</option>
                </select>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Location <span className="text-red-500">*</span>
                </label>
                <input
                  type="text"
                  name="location"
                  value={form.location}
                  onChange={handleChange}
                  required
                  className="input-field"
                  placeholder="Where did this occur?"
                  disabled={loading}
                />
              </div>
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Description <span className="text-red-500">*</span>
              </label>
              <textarea
                name="description"
                value={form.description}
                onChange={handleChange}
                rows={6}
                required
                className="input-field resize-none"
                placeholder="Please provide detailed information about your complaint..."
                disabled={loading}
              />
            </div>

            {/* Priority Selector */}
            <PrioritySelector
              value={form.priority_level}
              onChange={(priority) => setForm(prev => ({ ...prev, priority_level: priority }))}
            />

            {/* Image Upload Section */}
            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Upload Images (Optional)
              </label>
              <p className="text-xs text-gray-500 dark:text-gray-400 mb-3">
                Upload evidence photos (max 5 images, 5MB each). Supported formats: JPEG, PNG, GIF, WebP
              </p>
              
              <div className="space-y-3">
                <input
                  type="file"
                  accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
                  multiple
                  onChange={handleImageChange}
                  disabled={loading || selectedImages.length >= 5}
                  className="block w-full text-sm text-gray-500 dark:text-gray-400
                    file:mr-4 file:py-2 file:px-4
                    file:rounded-md file:border-0
                    file:text-sm file:font-semibold
                    file:bg-primary-50 file:text-primary-700
                    hover:file:bg-primary-100
                    dark:file:bg-primary-900 dark:file:text-primary-300
                    cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                />
                
                {/* Image Previews */}
                {imagePreviews.length > 0 && (
                  <div className="grid grid-cols-2 md:grid-cols-3 gap-4 mt-4">
                    {imagePreviews.map((preview, index) => (
                      <div key={index} className="relative group">
                        <img
                          src={preview}
                          alt={`Preview ${index + 1}`}
                          className="w-full h-32 object-cover rounded-lg border border-gray-200 dark:border-gray-700"
                        />
                        <button
                          type="button"
                          onClick={() => removeImage(index)}
                          disabled={loading}
                          className="absolute top-2 right-2 bg-red-500 text-white rounded-full p-1 opacity-0 group-hover:opacity-100 transition-opacity disabled:opacity-50"
                          title="Remove image"
                        >
                          <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                          </svg>
                        </button>
                        <p className="text-xs text-gray-500 mt-1 truncate">
                          {selectedImages[index]?.name || `Image ${index + 1}`}
                        </p>
                      </div>
                    ))}
                  </div>
                )}
                
                {selectedImages.length > 0 && (
                  <p className="text-xs text-gray-500">
                    {selectedImages.length} of 5 images selected
                  </p>
                )}
              </div>
            </div>

            {/* Legacy File Upload Section (for other file types) */}
            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Attach Other Files (Optional)
              </label>
              <p className="text-xs text-gray-500 dark:text-gray-400 mb-3">
                Upload documents or other files to support your complaint.
              </p>
              <MultiFileUpload
                onFilesUploaded={handleFilesUploaded}
                maxImages={0}
                maxVideos={1}
              />
            </div>

            <div className="flex gap-4 pt-4">
              <Button
                type="submit"
                variant="primary"
                size="lg"
                disabled={loading}
                className="flex-1"
              >
                {loading ? (
                  <>
                    <Loader2 className="w-4 h-4 mr-2 animate-spin" />
                    Submitting...
                  </>
                ) : (
                  <>
                    <FileText className="w-4 h-4 mr-2" />
                    Submit Complaint
                  </>
                )}
              </Button>
              <Button
                type="button"
                variant="secondary"
                onClick={() => navigate('/citizen')}
                disabled={loading}
              >
                Cancel
              </Button>
            </div>
          </form>
        </Card>
      </main>
    </div>
  );
};

export default SubmitComplaint;
