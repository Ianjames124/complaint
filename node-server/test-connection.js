/**
 * Simple test script to verify Socket.io server is running
 * Run: node test-connection.js
 */

const http = require('http');

const testHealth = () => {
  return new Promise((resolve, reject) => {
    const req = http.get('http://localhost:4000/health', (res) => {
      let data = '';
      
      res.on('data', (chunk) => {
        data += chunk;
      });
      
      res.on('end', () => {
        if (res.statusCode === 200) {
          try {
            const json = JSON.parse(data);
            resolve(json);
          } catch (e) {
            reject(new Error('Invalid JSON response'));
          }
        } else {
          reject(new Error(`Server returned status ${res.statusCode}`));
        }
      });
    });
    
    req.on('error', (error) => {
      reject(error);
    });
    
    req.setTimeout(5000, () => {
      req.destroy();
      reject(new Error('Connection timeout'));
    });
  });
};

console.log('🔍 Testing Socket.io server connection...\n');

testHealth()
  .then((data) => {
    console.log('✅ Server is running!');
    console.log('\nResponse:');
    console.log(JSON.stringify(data, null, 2));
    console.log('\n✅ Connection test passed!');
    process.exit(0);
  })
  .catch((error) => {
    console.error('❌ Server is NOT running!');
    console.error('\nError:', error.message);
    console.error('\n💡 Solution:');
    console.error('   1. Open a new terminal');
    console.error('   2. cd node-server');
    console.error('   3. npm start');
    console.error('\n   OR run: start.bat');
    process.exit(1);
  });

