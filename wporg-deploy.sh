#! /bin/bash
#
# WordPress.org SVN deploy script for easily publishing your WordPress plugins that primarily use the Git version control system.
#
# Deploy script came from here: https://github.com/thenbrent/multisite-user-management/blob/master/deploy.sh
# Which is a modification of Dean Clatworthy's deploy script as found here: https://github.com/deanc/wordpress-plugin-git-svn
# The difference is that this script lives in the plugin's git repo & doesn't require an existing SVN repo.

# This script needs to be executable (chmod +x wporg-deploy.sh)

# main config
PLUGINSLUG="aceide"
CURRENTDIR=`pwd`
MAINFILE="AceIDE.php" # this should be the name of your main php file in the wordpress plugin

# git config
GITPATH="$CURRENTDIR/" # this file should be in the base of your git repository

# svn config
SVNPATH="/tmp/$PLUGINSLUG" # path to a temp SVN repo. No trailing slash required and don't add trunk.
SVNURL="https://plugins.svn.wordpress.org/aceide" # Remote SVN repo on wordpress.org, with no trailing slash
SVNUSER="shanept" # your svn username
SVNIGNORE="
backups
.DS_Store
CHANGELOG.md" #other files you you don't need to publish to the WP.org repo


# Let's begin...
echo ".........................................."
echo
echo "Preparing to deploy wordpress plugin"
echo
echo ".........................................."
echo

# Check version in readme.txt is the same as plugin file
NEWVERSION1=`grep "^Stable tag" $GITPATH/readme.txt | awk -F' ' '{print $3}'`
echo "readme.txt version: $NEWVERSION1"
NEWVERSION2=`grep "^Stable tag" $GITPATH/README.md | awk -F' ' '{print $3}'`
echo "README.md version: $NEWVERSION2"
NEWVERSION3=`grep -m 1 "^#### [0-9\\.]*$" $GITPATH/CHANGELOG.md | awk -F' ' '{print $2}'`
echo "CHANGELOG.md version: $NEWVERSION3"
NEWVERSION4=`grep "^ \\* Version" $GITPATH/$MAINFILE | awk -F' ' '{print $3}'`
echo "$MAINFILE version: $NEWVERSION4"
echo

if [ "$NEWVERSION1" != "$NEWVERSION2" ]; then echo >&2 "Versions don't match. Exiting...."; exit 1; fi
if [ "$NEWVERSION1" != "$NEWVERSION3" ]; then echo >&2 "Versions don't match. Exiting...."; exit 1; fi
if [ "$NEWVERSION1" != "$NEWVERSION4" ]; then echo >&2 "Versions don't match. Exiting...."; exit 1; fi
command -v composer >/dev/null 2>&1 || { echo >&2 "Composer not installed.  Exiting...."; exit 1; }

echo "Versions all match. Let's proceed..."

cd $GITPATH
echo -e "Enter a commit message for this new version: \c"
read COMMITMSG
git commit -am "$COMMITMSG"

echo "Tagging new version in git"
git tag -a "$NEWVERSION1" -m "Tagging version $NEWVERSION1"

echo "Pushing latest commit to origin, with tags"
git push origin master
git push origin master --tags

echo
echo "Creating local copy of SVN repo ..."
svn co $SVNURL $SVNPATH

echo "Exporting the HEAD of master from git to the trunk of SVN"
git checkout-index -a -f --prefix=$SVNPATH/trunk/

echo "Ignoring github specific files and deployment script"
svn propset svn:ignore "wporg-deploy.sh
README.md
.git
.gitignore $SVNIGNORE" "$SVNPATH/trunk/"

echo "Changing directory to SVN"
cd $SVNPATH/trunk/


echo "Installing composer dependencies"
composer install --no-dev

echo "Committing to trunk"
# Add all new files that are not set to be ignored
svn status | grep -v "^.[ \t]*\..*" | grep "^?" | awk '{print $2}' | xargs svn add
svn commit --username=$SVNUSER -m "$COMMITMSG"

echo "Creating new SVN tag & committing it"
cd $SVNPATH
svn copy trunk/ tags/$NEWVERSION1/
cd $SVNPATH/tags/$NEWVERSION1
svn commit --username=$SVNUSER -m "Tagging version $NEWVERSION1"

echo "Removing temporary directory $SVNPATH"
rm -fr $SVNPATH/

echo "*** FIN ***"
